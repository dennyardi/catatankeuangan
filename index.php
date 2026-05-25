<?php
require_once 'includes/header.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$selectedPocketId = getSelectedPocketId($pdo, $user_id, $_GET['pocket_id'] ?? 'all', true);
$pockets = getUserPockets($pdo, $user_id, true);

// --- 1. AMBIL SETTINGAN CUTOFF USER ---
$stmtUser = $pdo->prepare("SELECT start_date_calculation FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$userSetting = $stmtUser->fetch();
$cutoffDay = (int)($userSetting['start_date_calculation'] ?? 1);

[$start_date, $end_date, $display_period] = calculatePeriodRange($cutoffDay);

// --- 3. QUERY DATA BERDASARKAN RANGE ---

$pocketFilterSql = $selectedPocketId === null ? "" : " AND pocket_id = ?";

// Total Pengeluaran di Periode Berjalan (Cutoff)
$sumParams = [$user_id, $start_date, $end_date];
if ($selectedPocketId !== null) $sumParams[] = $selectedPocketId;
$stmtSum = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND date BETWEEN ? AND ?" . $pocketFilterSql);
$stmtSum->execute($sumParams);
$totalMonth = $stmtSum->fetch()['total'] ?? 0;

// Total Khusus Hari Ini
$todayParams = [$user_id, $today];
if ($selectedPocketId !== null) $todayParams[] = $selectedPocketId;
$stmtToday = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND date = ?" . $pocketFilterSql);
$stmtToday->execute($todayParams);
$totalToday = $stmtToday->fetch()['total'] ?? 0;

// 5 Transaksi Terakhir (Tetap ambil yang paling baru secara global)
$stmtRecent = $pdo->prepare("
    SELECT e.*, c.name as category_name, p.name as pocket_name 
    FROM expenses e 
    LEFT JOIN categories c ON e.category_id = c.id 
    LEFT JOIN pockets p ON e.pocket_id = p.id 
    WHERE e.user_id = ? 
    " . ($selectedPocketId === null ? "" : "AND e.pocket_id = ?") . "
    ORDER BY e.date DESC, e.id DESC 
    LIMIT 5
");
$recentParams = [$user_id];
if ($selectedPocketId !== null) $recentParams[] = $selectedPocketId;
$stmtRecent->execute($recentParams);
$recentTx = $stmtRecent->fetchAll();

$stmtPocketSummary = $pdo->prepare("
    SELECT p.id, p.name, p.budget_amount, p.budget_enabled, COALESCE(SUM(e.amount), 0) AS total
    FROM pockets p
    LEFT JOIN expenses e ON e.pocket_id = p.id
        AND e.user_id = p.user_id
        AND e.date BETWEEN ? AND ?
    WHERE p.user_id = ?
      AND p.is_active = 1
    GROUP BY p.id, p.name, p.budget_amount, p.budget_enabled
    ORDER BY p.name ASC
");
$stmtPocketSummary->execute([$start_date, $end_date, $user_id]);
$pocketSummaries = $stmtPocketSummary->fetchAll();

$categoryLimitSql = "
    SELECT c.id, c.name, c.pocket_id, c.budget_amount, c.budget_enabled, p.name AS pocket_name, COALESCE(SUM(e.amount), 0) AS total
    FROM categories c
    LEFT JOIN pockets p ON c.pocket_id = p.id
    LEFT JOIN expenses e ON e.category_id = c.id
        AND e.user_id = ?
        AND e.date BETWEEN ? AND ?
        " . ($selectedPocketId !== null ? "AND e.pocket_id = ?" : "") . "
    WHERE (c.user_id IS NULL OR c.user_id = ?)
      AND c.budget_enabled = 1
      AND c.budget_amount > 0
      " . ($selectedPocketId !== null ? "AND (c.pocket_id IS NULL OR c.pocket_id = ?)" : "") . "
    GROUP BY c.id, c.name, c.pocket_id, c.budget_amount, c.budget_enabled, p.name
    ORDER BY c.pocket_id IS NULL DESC, c.name ASC
";
$categoryLimitParams = [$user_id, $start_date, $end_date];
if ($selectedPocketId !== null) $categoryLimitParams[] = $selectedPocketId;
$categoryLimitParams[] = $user_id;
if ($selectedPocketId !== null) $categoryLimitParams[] = $selectedPocketId;
$stmtCategoryLimits = $pdo->prepare($categoryLimitSql);
$stmtCategoryLimits->execute($categoryLimitParams);
$categoryLimits = $stmtCategoryLimits->fetchAll();
?>

<div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Ringkasan Keuangan</h1>
        <p class="text-slate-500 text-sm">
            Periode: <span class="font-semibold text-indigo-600"><?= $display_period ?></span> 
            <?php if($cutoffDay > 1): ?>
                <span class="text-xs bg-slate-100 px-2 py-1 rounded ml-2">Cutoff Tgl <?= $cutoffDay ?></span>
            <?php endif; ?>
        </p>
    </div>
    <form method="GET" class="w-full md:w-64">
        <select name="pocket_id" onchange="this.form.submit()" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
            <option value="all" <?= $selectedPocketId === null ? 'selected' : '' ?>>Semua Pocket</option>
            <?php foreach ($pockets as $pocket): ?>
                <option value="<?= (int)$pocket['id'] ?>" <?= (int)$selectedPocketId === (int)$pocket['id'] ? 'selected' : '' ?>><?= e($pocket['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="mb-8">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-slate-800">Ringkasan Per Pocket</h2>
        <a href="pockets.php" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Atur budget</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($pocketSummaries as $summary): ?>
            <?php
                $budget = !empty($summary['budget_enabled']) ? (float)$summary['budget_amount'] : 0;
                $spent = (float)$summary['total'];
                $percent = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
                $remaining = $budget - $spent;
                $barClass = $budget > 0 && $spent > $budget ? 'bg-red-500' : ($percent >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-slate-800 truncate"><?= e($summary['name']) ?></h3>
                        <p class="text-xs text-slate-400 mt-1">Periode <?= e($display_period) ?></p>
                    </div>
                    <a href="transactions.php?pocket_id=<?= (int)$summary['id'] ?>" class="text-xs px-2 py-1 rounded border border-indigo-100 bg-indigo-50 text-indigo-700">Detail</a>
                </div>
                <p class="text-xs font-semibold uppercase text-slate-500 mb-1">Terpakai</p>
                <p class="text-xl font-bold text-slate-900"><?= formatRupiah($spent) ?></p>
                <div class="mt-4">
                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                        <span>Limit <?= !empty($summary['budget_enabled']) ? ($budget > 0 ? formatRupiah($budget) : 'aktif tanpa nominal') : 'nonaktif' ?></span>
                        <?php if ($budget > 0): ?>
                            <span><?= number_format($percent, 0) ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full <?= $barClass ?>" style="width: <?= e($percent) ?>%"></div>
                    </div>
                    <p class="text-xs mt-2 <?= $budget > 0 && $remaining < 0 ? 'text-red-600' : 'text-slate-500' ?>">
                        <?php if ($budget > 0): ?>
                            <?= $remaining >= 0 ? 'Sisa ' . formatRupiah($remaining) : 'Lewat budget ' . formatRupiah(abs($remaining)) ?>
                        <?php else: ?>
                            Aktifkan limit pocket untuk memantau progress.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!empty($categoryLimits)): ?>
<div class="mb-8">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-slate-800">Limit Per Kategori</h2>
        <a href="category_rules.php" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Atur kategori</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($categoryLimits as $categoryLimit): ?>
            <?php
                $catBudget = (float)$categoryLimit['budget_amount'];
                $catSpent = (float)$categoryLimit['total'];
                $catPercent = $catBudget > 0 ? min(100, ($catSpent / $catBudget) * 100) : 0;
                $catRemaining = $catBudget - $catSpent;
                $catBarClass = $catSpent > $catBudget ? 'bg-red-500' : ($catPercent >= 80 ? 'bg-amber-500' : 'bg-sky-500');
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-slate-800 truncate"><?= e($categoryLimit['name']) ?></h3>
                        <p class="text-xs text-slate-400 mt-1"><?= $categoryLimit['pocket_id'] ? e($categoryLimit['pocket_name']) : 'Semua Pocket' ?></p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded border border-sky-100 bg-sky-50 text-sky-700">Kategori</span>
                </div>
                <p class="text-xs font-semibold uppercase text-slate-500 mb-1">Terpakai</p>
                <p class="text-xl font-bold text-slate-900"><?= formatRupiah($catSpent) ?></p>
                <div class="mt-4">
                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                        <span>Limit <?= formatRupiah($catBudget) ?></span>
                        <span><?= number_format($catPercent, 0) ?>%</span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full <?= $catBarClass ?>" style="width: <?= e($catPercent) ?>%"></div>
                    </div>
                    <p class="text-xs mt-2 <?= $catRemaining < 0 ? 'text-red-600' : 'text-slate-500' ?>">
                        <?= $catRemaining >= 0 ? 'Sisa ' . formatRupiah($catRemaining) : 'Lewat limit ' . formatRupiah(abs($catRemaining)) ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
    <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-xl shadow-lg p-6 text-white relative overflow-hidden">
        <div class="relative z-10">
            <p class="text-indigo-100 text-sm font-medium mb-1">Total Pengeluaran Periode Ini</p>
            <h2 class="text-3xl font-bold"><?= formatRupiah($totalMonth) ?></h2>
        </div>
        <div class="absolute right-4 bottom-4 opacity-10">
            <i data-lucide="pie-chart" class="w-20 h-20 text-white"></i>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center justify-between">
        <div>
            <p class="text-slate-500 text-sm font-medium mb-1">Pengeluaran Hari Ini</p>
            <h2 class="text-2xl font-bold text-slate-800"><?= formatRupiah($totalToday) ?></h2>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-1"><?= date('l, d F') ?></p>
        </div>
        <div class="p-3 bg-emerald-50 rounded-full">
            <i data-lucide="banknote" class="w-6 h-6 text-emerald-600"></i>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
        <h3 class="font-semibold text-slate-800">Aktivitas Terkini</h3>
        <a href="input.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-700 flex items-center gap-1">
            <i data-lucide="plus" class="w-4 h-4"></i> Catat Baru
        </a>
    </div>
    
    <div class="divide-y divide-slate-100">
        <?php if (empty($recentTx)): ?>
            <div class="p-8 text-center text-slate-400 text-sm">Belum ada aktivitas tercatat.</div>
        <?php else: ?>
            <?php foreach($recentTx as $row): ?>
            <div class="px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                        <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-800"><?= e($row['description']) ?></p>
                        <p class="text-xs text-slate-500"><?= date('d M Y', strtotime($row['date'])) ?> &bull; <?= e($row['category_name']) ?> &bull; <?= e($row['pocket_name'] ?? 'Pocket lama') ?></p>
                    </div>
                </div>
                <span class="font-semibold text-slate-700 text-sm"><?= formatRupiah($row['amount']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-center">
        <a href="transactions.php" class="text-xs font-medium text-slate-500 hover:text-slate-800 transition">Lihat Seluruh Riwayat &rarr;</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
