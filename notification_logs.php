<?php
require_once 'includes/header.php';
checkLogin();

$user_id = (int)$_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all';
$period = $_GET['period'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$limit = (int)($_GET['limit'] ?? 20);
$page = (int)($_GET['page'] ?? 1);

if (!in_array($status, ['all', 'sent', 'failed', 'error'], true)) $status = 'all';
if (!in_array($period, ['all', 'weekly', 'monthly'], true)) $period = 'all';
if (!in_array($type, ['all', 'test', 'auto'], true)) $type = 'all';
if (!in_array($limit, [10, 20, 50], true)) $limit = 20;
if ($page < 1) $page = 1;

$where = ['nl.user_id = ?'];
$params = [$user_id];

if ($search !== '') {
    $where[] = '(nl.group_id LIKE ? OR ns.name LIKE ? OR nl.message_preview LIKE ?)';
    $keyword = '%' . $search . '%';
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

if ($status !== 'all') {
    $where[] = 'nl.status = ?';
    $params[] = $status;
}

if ($period !== 'all') {
    $where[] = 'nl.period = ?';
    $params[] = $period;
}

if ($type === 'test') {
    $where[] = 'nl.is_test = 1';
} elseif ($type === 'auto') {
    $where[] = 'nl.is_test = 0';
}

$whereSql = implode(' AND ', $where);

$stmtCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM notification_logs nl
    LEFT JOIN notification_settings ns ON ns.id = nl.notification_setting_id
    WHERE $whereSql
");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

$stmtLogs = $pdo->prepare("
    SELECT nl.*, ns.name AS setting_name, p.name AS pocket_name
    FROM notification_logs nl
    LEFT JOIN notification_settings ns ON ns.id = nl.notification_setting_id
    LEFT JOIN pockets p ON p.id = ns.pocket_id
    WHERE $whereSql
    ORDER BY nl.sent_at DESC, nl.id DESC
    LIMIT $limit OFFSET $offset
");
$stmtLogs->execute($params);
$logs = $stmtLogs->fetchAll();

$baseQuery = [
    'search' => $search,
    'status' => $status,
    'period' => $period,
    'type' => $type,
    'limit' => $limit
];

function notificationLogUrl(array $baseQuery, $page) {
    $baseQuery['page'] = $page;
    return 'notification_logs.php?' . http_build_query($baseQuery);
}
?>

<div class="mb-6 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Histori Notifikasi</h1>
        <p class="text-slate-500 text-sm">Pantau semua pengiriman summary dari cron dan test manual.</p>
    </div>
    <a href="notifications.php" class="inline-flex items-center justify-center px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm transition">Konfigurasi Notifikasi</a>
</div>

<form method="GET" class="mb-6 bg-white rounded-xl shadow-sm border border-slate-200 p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
        <div class="xl:col-span-2">
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Search</label>
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Nama, Group ID, isi pesan"
                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                <option value="error" <?= $status === 'error' ? 'selected' : '' ?>>Error</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Periode</label>
            <select name="period" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
                <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Tipe</label>
            <select name="type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none">
                <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="auto" <?= $type === 'auto' ? 'selected' : '' ?>>Cron</option>
                <option value="test" <?= $type === 'test' ? 'selected' : '' ?>>Test</option>
            </select>
        </div>
    </div>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-sm transition">Terapkan Filter</button>
        <div class="flex items-center gap-2 text-sm text-slate-600">
            <span>Tampilkan</span>
            <select name="limit" onchange="this.form.submit()" class="px-2 py-1.5 border border-slate-300 rounded-lg text-sm bg-white">
                <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                <option value="20" <?= $limit === 20 ? 'selected' : '' ?>>20</option>
                <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
            </select>
            <span>data</span>
        </div>
    </div>
</form>

<section class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-slate-800">Semua Log</h2>
        <span class="text-xs text-slate-500"><?= (int)$totalRows ?> data</span>
    </div>

    <?php if (!$logs): ?>
        <div class="p-8 text-center text-sm text-slate-500">Belum ada log yang cocok dengan filter.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 text-left font-semibold">Waktu</th>
                        <th class="px-5 py-3 text-left font-semibold">Notifikasi</th>
                        <th class="px-5 py-3 text-left font-semibold">Periode</th>
                        <th class="px-5 py-3 text-left font-semibold">Group ID</th>
                        <th class="px-5 py-3 text-left font-semibold">Status</th>
                        <th class="px-5 py-3 text-left font-semibold">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($logs as $log): ?>
                        <tr class="align-top">
                            <td class="px-5 py-3 whitespace-nowrap text-slate-600"><?= e(date('d M Y H:i', strtotime($log['sent_at']))) ?></td>
                            <td class="px-5 py-3 min-w-[180px]">
                                <div class="font-medium text-slate-800"><?= e($log['setting_name'] ?: 'Konfigurasi terhapus') ?></div>
                                <div class="text-xs text-slate-500"><?= $log['pocket_name'] ? e($log['pocket_name']) : 'Semua Pocket' ?></div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="capitalize"><?= e($log['period']) ?></span>
                                <?php if (!empty($log['is_test'])): ?>
                                    <span class="ml-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100 text-xs">Test</span>
                                <?php else: ?>
                                    <span class="ml-1 px-2 py-0.5 rounded-full bg-slate-50 text-slate-600 border border-slate-200 text-xs">Cron</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 max-w-[240px] truncate text-slate-600"><?= e($log['group_id']) ?></td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-1 rounded-full text-xs border <?= $log['status'] === 'sent' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
                                    <?= e($log['status']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-xs text-slate-500 min-w-[280px] max-w-[420px]">
                                <?php if ($log['gateway_status']): ?>
                                    <div class="mb-1">Gateway: <?= (int)$log['gateway_status'] ?></div>
                                <?php endif; ?>
                                <?php if ($log['error_message']): ?>
                                    <div class="text-red-600 whitespace-pre-wrap"><?= e($log['error_message']) ?></div>
                                <?php else: ?>
                                    <details>
                                        <summary class="cursor-pointer text-indigo-600 hover:text-indigo-700">Lihat isi pesan</summary>
                                        <pre class="mt-2 whitespace-pre-wrap font-sans leading-5 text-slate-600"><?= e($log['message_preview']) ?></pre>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($totalPages > 1): ?>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Halaman <?= (int)$page ?> dari <?= (int)$totalPages ?></p>
        <div class="flex items-center gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e(notificationLogUrl($baseQuery, $page - 1)) ?>" class="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm">Sebelumnya</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= e(notificationLogUrl($baseQuery, $page + 1)) ?>" class="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm">Berikutnya</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
