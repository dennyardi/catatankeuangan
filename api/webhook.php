<?php
// api/webhook.php
// Pencatatan keuangan via WhatsApp dengan routing multi-pocket berbasis Group ID.

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$localConfigPath = __DIR__ . '/../config/local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) $localConfig = [];

$allowed_numbers = array_filter(array_map('trim', explode(',', getenv('WA_ALLOWED_NUMBERS') ?: ($localConfig['wa_allowed_numbers'] ?? ''))));

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    exit(json_encode(['status' => 'ignored', 'message' => 'No data']));
}

$webhookDebug = getenv('WEBHOOK_DEBUG') ?: ($localConfig['webhook_debug'] ?? '0');
if ($webhookDebug === '1') {
    file_put_contents(__DIR__ . '/webhook_debug.log', '[' . date('Y-m-d H:i:s') . '] JSON: ' . substr($json, 0, 4000) . PHP_EOL, FILE_APPEND);
}

$raw_sender = $data['from'] ?? ($data['payload']['key']['remoteJid'] ?? '');
$remote_jid = $data['payload']['key']['remoteJid'] ?? $raw_sender;
$group_id = endsWith((string)$remote_jid, '@g.us') ? $remote_jid : (endsWith((string)$raw_sender, '@g.us') ? $raw_sender : '');
$clean_number = str_replace(['@s.whatsapp.net', '+'], '', (string)$raw_sender);

$pocket = findPocketByGroupId($pdo, $group_id);
$is_group_message = $group_id !== '';
$is_allowed = $is_group_message ? (bool)$pocket : in_array($clean_number, $allowed_numbers, true);

if (!$is_allowed) {
    http_response_code(403);
    exit(json_encode(['status' => 'rejected', 'message' => 'Unauthorized sender']));
}

$message = '';
if (!empty($data['text'])) {
    $message = $data['text'];
} elseif (!empty($data['payload']['message']['conversation'])) {
    $message = $data['payload']['message']['conversation'];
} elseif (!empty($data['payload']['message']['extendedTextMessage']['text'])) {
    $message = $data['payload']['message']['extendedTextMessage']['text'];
} elseif (!empty($data['message'])) {
    $message = $data['message'];
}

if (trim($message) === '') {
    exit(json_encode(['status' => 'ignored', 'message' => 'Empty message']));
}

$nominal = 0;
$deskripsi = '';
$clean_msg = strtolower(trim($message));

if (preg_match('/(\d+(?:[.,]\d+)?)\s*(k|rb|000)?/i', $clean_msg, $matches)) {
    $angka_mentah = str_replace([',', '.'], '', $matches[1]);
    $suffix = strtolower($matches[2] ?? '');
    $nominal = ($suffix === 'k' || $suffix === 'rb') ? ((int)$angka_mentah * 1000) : (int)$angka_mentah;
    $deskripsi = trim(str_replace($matches[0], '', $clean_msg));
} else {
    exit(json_encode(['status' => 'ignored', 'message' => 'Not a transaction']));
}

if ($nominal <= 0) {
    exit(json_encode(['status' => 'ignored', 'message' => 'Invalid amount']));
}

if ($deskripsi === '') $deskripsi = 'Pengeluaran Tanpa Keterangan';

try {
    $user_id = $pocket ? (int)$pocket['user_id'] : 1;
    $pocket_id = $pocket ? (int)$pocket['id'] : getDefaultPocketId($pdo, $user_id);
    $pocket_name = $pocket['name'] ?? 'Default Pocket';

    $categoryGuess = guessCategoryForExpense($pdo, $user_id, $pocket_id, $deskripsi);
    if (!$categoryGuess) {
        throw new RuntimeException('Category table is empty.');
    }
    $kategori_id = $categoryGuess['id'];
    $kategori_nama = $categoryGuess['name'];

    $stmt = $pdo->prepare("INSERT INTO expenses (user_id, pocket_id, amount, category_id, description, date, source) VALUES (?, ?, ?, ?, ?, CURDATE(), 'wa')");
    $stmt->execute([$user_id, $pocket_id, $nominal, $kategori_id, ucwords($deskripsi)]);

    $stmtUser = $pdo->prepare("SELECT start_date_calculation FROM users WHERE id = ?");
    $stmtUser->execute([$user_id]);
    $userSetting = $stmtUser->fetch();
    $cutoffDay = (int)($userSetting['start_date_calculation'] ?? 1);
    [$calc_start_date, $calc_end_date] = calculatePeriodRange($cutoffDay);

    $stmtTotal = $pdo->prepare("
        SELECT SUM(amount) as total
        FROM expenses
        WHERE user_id = ?
          AND pocket_id = ?
          AND date BETWEEN ? AND ?
    ");
    $stmtTotal->execute([$user_id, $pocket_id, $calc_start_date, $calc_end_date]);
    $total_periode_ini = $stmtTotal->fetch()['total'] ?? 0;

    $info_periode = ($cutoffDay === 1)
        ? 'Bulan Ini'
        : 'Periode (' . date('d M', strtotime($calc_start_date)) . ' - ' . date('d M', strtotime($calc_end_date)) . ')';

    $reply = "Tersimpan!\n"
        . ucwords($deskripsi) . "\n"
        . "Rp " . number_format($nominal, 0, ',', '.') . "\n"
        . "Kategori: " . $kategori_nama . "\n"
        . "Pocket: " . $pocket_name . "\n\n"
        . "Total " . $info_periode . ":\n"
        . "Rp " . number_format($total_periode_ini, 0, ',', '.');

    sendWhatsAppMessage($raw_sender, $reply);
    echo json_encode(['status' => 'saved', 'pocket_id' => $pocket_id]);
} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/webhook_error.log', '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    sendWhatsAppMessage($raw_sender, 'Gagal menyimpan transaksi. Silakan cek log server.');
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}

function endsWith($value, $suffix) {
    $value = (string)$value;
    $suffix = (string)$suffix;
    if ($suffix === '') return true;
    return substr($value, -strlen($suffix)) === $suffix;
}
