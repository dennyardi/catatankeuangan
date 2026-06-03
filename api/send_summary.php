<?php
// api/send_summary.php
// Endpoint cron untuk mengirim ringkasan keuangan mingguan atau bulanan.

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$period = $_GET['period'] ?? '';
$force = ($_GET['force'] ?? '') === '1';
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$localConfigPath = __DIR__ . '/../config/local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($localConfig)) $localConfig = [];
$cronToken = trim((string)($localConfig['summary_cron_token'] ?? ''));
if ($cronToken === '') {
    $cronToken = trim((string)(getenv('SUMMARY_CRON_TOKEN') ?: ''));
}
$providedToken = trim((string)($_GET['key'] ?? ''));
$isCli = PHP_SAPI === 'cli';

if (!$isCli && ($cronToken === '' || !hash_equals($cronToken, $providedToken))) {
    http_response_code(403);
    exit(json_encode([
        'status' => 'rejected',
        'message' => 'Invalid summary token.'
    ]));
}

if (!in_array($period, ['weekly', 'monthly'], true)) {
    http_response_code(400);
    exit(json_encode([
        'status' => 'error',
        'message' => 'period must be weekly or monthly.'
    ]));
}

$dayOfWeek = (int)date('N');
$dayOfMonth = (int)date('j');
$sentColumn = $period === 'weekly' ? 'last_weekly_sent_at' : 'last_monthly_sent_at';
$enabledColumn = $period === 'weekly' ? 'weekly_enabled' : 'monthly_enabled';
$scheduleColumn = $period === 'weekly' ? 'weekly_day' : 'monthly_day';
$scheduleValue = $period === 'weekly' ? $dayOfWeek : $dayOfMonth;

$sql = "
    SELECT ns.*
    FROM notification_settings ns
    LEFT JOIN pockets p ON p.id = ns.pocket_id
    WHERE ns.is_active = 1
      AND ns.$enabledColumn = 1
      AND (ns.pocket_id IS NULL OR p.is_active = 1)
";
$params = [];

if (!$force) {
    $sql .= " AND ns.$scheduleColumn = ? AND (ns.$sentColumn IS NULL OR DATE(ns.$sentColumn) <> ?)";
    $params[] = $scheduleValue;
    $params[] = $today;
}

$sql .= " ORDER BY ns.user_id ASC, ns.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$settings = $stmt->fetchAll();

$results = [];
$sentCount = 0;
$failedCount = 0;

foreach ($settings as $setting) {
    try {
        $sendResult = sendSummaryNotification($pdo, $setting, $period, false);
        $ok = (bool)$sendResult['ok'];

        if ($ok) {
            $stmtUpdate = $pdo->prepare("UPDATE notification_settings SET $sentColumn = ? WHERE id = ?");
            $stmtUpdate->execute([$now, (int)$setting['id']]);
            $sentCount++;
        } else {
            $failedCount++;
        }

        $results[] = [
            'id' => (int)$setting['id'],
            'name' => $setting['name'],
            'group_id' => $setting['group_id'],
            'status' => $ok ? 'sent' : 'failed',
            'gateway_status' => $sendResult['gateway_status']
        ];
    } catch (Throwable $e) {
        $failedCount++;
        recordNotificationLog($pdo, $setting, $period, 'error', null, $e->getMessage(), null, false);
        $results[] = [
            'id' => (int)$setting['id'],
            'name' => $setting['name'] ?? '',
            'group_id' => $setting['group_id'] ?? '',
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

echo json_encode([
    'status' => 'done',
    'period' => $period,
    'matched' => count($settings),
    'sent' => $sentCount,
    'failed' => $failedCount,
    'results' => $results
]);
