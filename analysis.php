<?php
require_once 'includes/header.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$selectedPocketId = getSelectedPocketId($pdo, $user_id, $_GET['pocket_id'] ?? 'all', true);
$pockets = getUserPockets($pdo, $user_id, true);
$pocketSql = $selectedPocketId === null ? '' : ' AND pocket_id = ?';
$pocketSqlAlias = $selectedPocketId === null ? '' : ' AND e.pocket_id = ?';

// --- 0. AMBIL SETTINGAN CUTOFF USER ---
$stmtUser = $pdo->prepare("SELECT start_date_calculation FROM users WHERE id = ?");
$stmtUser->execute([$user_id]);
$userSetting = $stmtUser->fetch();
$cutoffDay = (int)($userSetting['start_date_calculation'] ?? 1); 

// --- 1. KONFIGURASI PERIODE (FILTER UTAMA) ---
$selected_month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');

if ($cutoffDay == 1) {
    // MODE KALENDER (Standar)
    $start_date = $selected_month . '-01';
    $end_date   = date('Y-m-t', strtotime($start_date));
    
    // Untuk Query 6 Bulan Terakhir (Mundur 5 bulan dari awal bulan ini)
    $sixMonthStart = date('Y-m-01', strtotime("$start_date -5 months"));
    
    $display_period = date('F Y', strtotime($start_date));

} else {
    // MODE CUSTOM (Misal: Tanggal 25)
    // Periode: 25 Bulan Lalu s/d 24 Bulan Ini
    
    $dateObj = DateTime::createFromFormat('Y-m', $selected_month);
    
    // End date = Tanggal 24 bulan ini
    $end_day_val = $cutoffDay - 1;
    $end_date = $dateObj->format('Y-m-') . sprintf("%02d", $end_day_val);
    
    // Start date = Tanggal 25 bulan lalu
    $start_date = $dateObj->modify('-1 month')->format('Y-m-') . sprintf("%02d", $cutoffDay);

    // Untuk Query 6 Bulan Terakhir
    // Kita butuh data mulai dari: Tanggal 25, 6 bulan sebelum bulan ini
    $sixMonthObj = new DateTime($start_date); 
    $sixMonthStart = $sixMonthObj->modify('-5 months')->format('Y-m-d');

    $display_period = date('d M', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date));
}

// --- 2. HITUNG TOTAL & AVG PERIODE INI ---
// Total Saat Ini
$periodParams = [$user_id, $start_date, $end_date];
if ($selectedPocketId !== null) $periodParams[] = $selectedPocketId;
$stmtTotal = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND date BETWEEN ? AND ?" . $pocketSql);
$stmtTotal->execute($periodParams);
$grandTotal = (float) ($stmtTotal->fetch()['total'] ?? 0);

// Total Periode Sebelumnya (Untuk persentase)
// Mundur 1 bulan persis dari range start_date & end_date
$prev_start = date('Y-m-d', strtotime("$start_date -1 month"));
$prev_end   = date('Y-m-d', strtotime("$end_date -1 month"));

$prevParams = [$user_id, $prev_start, $prev_end];
if ($selectedPocketId !== null) $prevParams[] = $selectedPocketId;
$stmtLast = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE user_id = ? AND date BETWEEN ? AND ?" . $pocketSql);
$stmtLast->execute($prevParams);
$lastMonthTotal = (float) ($stmtLast->fetch()['total'] ?? 0);

$percentageChange = 0;
if ($lastMonthTotal > 0) {
    $percentageChange = (($grandTotal - $lastMonthTotal) / $lastMonthTotal) * 100;
}

// Rata-rata Harian
$today = date('Y-m-d');
$daysPassed = 0;
if ($today >= $start_date && $today <= $end_date) {
    $diff = abs(strtotime($today) - strtotime($start_date));
    $daysPassed = floor($diff / (60*60*24)) + 1;
} else {
    $diff = abs(strtotime($end_date) - strtotime($start_date));
    $daysPassed = floor($diff / (60*60*24)) + 1;
}
$dailyAverage = ($daysPassed > 0) ? $grandTotal / $daysPassed : 0;


// --- 3. DATA TREN 6 BULAN (LOGIKA BARU) ---
// Kita perlu Query yang mengelompokkan data berdasarkan "Bulan Pembukuan"
// Bukan bulan kalender.

$sqlMonthly = "";
if ($cutoffDay == 1) {
    // Query Standar
    $sqlMonthly = "
        SELECT DATE_FORMAT(date, '%Y-%m') as mo, SUM(amount) as total 
        FROM expenses 
        WHERE user_id = ? AND date >= ?" . $pocketSql . "
        GROUP BY mo
    ";
} else {
    // Query Custom Cutoff
    // Logika: Jika tgl >= 25, maka masuk bulan berikutnya.
    // Contoh: 26 Jan masuk periode Feb. 15 Jan masuk periode Jan.
    $sqlMonthly = "
        SELECT 
            CASE 
                WHEN DAY(date) >= $cutoffDay THEN DATE_FORMAT(DATE_ADD(date, INTERVAL 1 MONTH), '%Y-%m')
                ELSE DATE_FORMAT(date, '%Y-%m')
            END as mo,
            SUM(amount) as total
        FROM expenses
        WHERE user_id = ? AND date >= ?" . $pocketSql . "
        GROUP BY mo
    ";
}

$stmtMonthly = $pdo->prepare($sqlMonthly);
$monthlyParams = [$user_id, $sixMonthStart];
if ($selectedPocketId !== null) $monthlyParams[] = $selectedPocketId;
$stmtMonthly->execute($monthlyParams);
$rows = $stmtMonthly->fetchAll(PDO::FETCH_ASSOC);

// Mapping hasil query ke Array Key-Value agar mudah dicocokkan
$dbData = [];
foreach($rows as $r) {
    $dbData[$r['mo']] = (float)$r['total'];
}

// Generate 6 Bulan Terakhir (Termasuk bulan yg dipilih) untuk sumbu X
$monthlyData = [];
$maxMonthly = 1;

// Loop mundur dari 5 bulan lalu sampai 0 (bulan ini)
for ($i = 5; $i >= 0; $i--) {
    // Hitung Key Bulan (YYYY-MM) berdasarkan bulan yang dipilih user
    $monthKey = date('Y-m', strtotime("$selected_month -$i months"));
    
    $total = $dbData[$monthKey] ?? 0;
    if ($total > $maxMonthly) $maxMonthly = $total;

    $monthlyData[] = [
        'mo' => $monthKey,
        'month_name' => date('M', strtotime($monthKey . '-01')), // Tampilkan nama bulan (Jan, Feb)
        'total' => $total
    ];
}


// --- 4. DATA HARIAN (LINE CHART) ---
// Menggunakan Loop DatePeriod agar presisi
$stmtDaily = $pdo->prepare("
    SELECT date, SUM(amount) as total 
    FROM expenses 
    WHERE user_id = ? AND date BETWEEN ? AND ?" . $pocketSql . "
    GROUP BY date 
    ORDER BY date ASC
");
$stmtDaily->execute($periodParams);
$dailyRows = $stmtDaily->fetchAll(PDO::FETCH_ASSOC);

$dailyMap = [];
foreach ($dailyRows as $r) {
    $dailyMap[$r['date']] = (float)$r['total'];
}

$chartLabels = []; 
$chartData   = []; 

$begin = new DateTime($start_date);
$end   = new DateTime($end_date);
$end->modify('+1 day'); 
$period = new DatePeriod($begin, DateInterval::createFromDateString('1 day'), $end);

foreach ($period as $dt) {
    $currentDate = $dt->format("Y-m-d");
    
    // Label Sumbu X Chart Harian
    if ($cutoffDay == 1) {
        $chartLabels[] = $dt->format("d"); 
    } else {
        $chartLabels[] = $dt->format("d M"); // Tampilkan tgl & bulan (25 Jan)
    }
    
    $val = $dailyMap[$currentDate] ?? 0;
    $chartData[] = ($val == 0) ? null : $val;
}


// --- 5. DATA LAINNYA ---
$catParams = [$user_id, $start_date, $end_date];
if ($selectedPocketId !== null) $catParams[] = $selectedPocketId;
$stmtCat = $pdo->prepare("SELECT c.name, SUM(e.amount) as total FROM expenses e JOIN categories c ON e.category_id = c.id WHERE e.user_id = ? AND e.date BETWEEN ? AND ?" . $pocketSqlAlias . " GROUP BY c.name ORDER BY total DESC");
$stmtCat->execute($catParams);
$categories = $stmtCat->fetchAll();

$stmtTop = $pdo->prepare("SELECT description, amount, date FROM expenses WHERE user_id = ? AND date BETWEEN ? AND ?" . $pocketSql . " ORDER BY amount DESC LIMIT 5");
$stmtTop->execute($periodParams);
$topExpenses = $stmtTop->fetchAll();

// --- 6. INSIGHT TAMBAHAN ---
$totalDaysInPeriod = floor((strtotime($end_date) - strtotime($start_date)) / 86400) + 1;
$projectedTotal = $dailyAverage * max(1, $totalDaysInPeriod);

$budgetParams = [$start_date, $end_date, $user_id];
$budgetPocketFilter = '';
if ($selectedPocketId !== null) {
    $budgetPocketFilter = ' AND p.id = ?';
    $budgetParams[] = $selectedPocketId;
}
$stmtBudgetHealth = $pdo->prepare("
    SELECT p.id, p.name, p.budget_amount, p.budget_enabled, COALESCE(SUM(e.amount), 0) AS total
    FROM pockets p
    LEFT JOIN expenses e ON e.pocket_id = p.id
        AND e.user_id = p.user_id
        AND e.date BETWEEN ? AND ?
    WHERE p.user_id = ?
      AND p.is_active = 1
      $budgetPocketFilter
    GROUP BY p.id, p.name, p.budget_amount, p.budget_enabled
    ORDER BY p.name ASC
");
$stmtBudgetHealth->execute($budgetParams);
$budgetHealth = $stmtBudgetHealth->fetchAll();

$stmtPocketCompare = $pdo->prepare("
    SELECT p.id, p.name,
        COALESCE(SUM(CASE WHEN e.date BETWEEN ? AND ? THEN e.amount ELSE 0 END), 0) AS current_total,
        COALESCE(SUM(CASE WHEN e.date BETWEEN ? AND ? THEN e.amount ELSE 0 END), 0) AS previous_total
    FROM pockets p
    LEFT JOIN expenses e ON e.pocket_id = p.id
        AND e.user_id = p.user_id
        AND e.date BETWEEN ? AND ?
    WHERE p.user_id = ?
      AND p.is_active = 1
      " . ($selectedPocketId !== null ? "AND p.id = ?" : "") . "
    GROUP BY p.id, p.name
    ORDER BY current_total DESC
");
$pocketCompareParams = [$start_date, $end_date, $prev_start, $prev_end, $prev_start, $end_date, $user_id];
if ($selectedPocketId !== null) $pocketCompareParams[] = $selectedPocketId;
$stmtPocketCompare->execute($pocketCompareParams);
$pocketCompare = $stmtPocketCompare->fetchAll();

$stmtCategoryCompare = $pdo->prepare("
    SELECT c.name,
        COALESCE(SUM(CASE WHEN e.date BETWEEN ? AND ? THEN e.amount ELSE 0 END), 0) AS current_total,
        COALESCE(SUM(CASE WHEN e.date BETWEEN ? AND ? THEN e.amount ELSE 0 END), 0) AS previous_total
    FROM categories c
    JOIN expenses e ON e.category_id = c.id
    WHERE e.user_id = ?
      AND e.date BETWEEN ? AND ?
      " . ($selectedPocketId !== null ? "AND e.pocket_id = ?" : "") . "
    GROUP BY c.id, c.name
    HAVING current_total > 0 OR previous_total > 0
    ORDER BY (current_total - previous_total) DESC
    LIMIT 6
");
$categoryCompareParams = [$start_date, $end_date, $prev_start, $prev_end, $user_id, $prev_start, $end_date];
if ($selectedPocketId !== null) $categoryCompareParams[] = $selectedPocketId;
$stmtCategoryCompare->execute($categoryCompareParams);
$categoryCompare = $stmtCategoryCompare->fetchAll();

$stmtCategoryAvg = $pdo->prepare("
    SELECT category_id, AVG(amount) AS avg_amount
    FROM expenses
    WHERE user_id = ?
      AND date BETWEEN ? AND ?
      " . ($selectedPocketId !== null ? "AND pocket_id = ?" : "") . "
    GROUP BY category_id
");
$categoryAvgParams = [$user_id, $start_date, $end_date];
if ($selectedPocketId !== null) $categoryAvgParams[] = $selectedPocketId;
$stmtCategoryAvg->execute($categoryAvgParams);
$categoryAverages = [];
foreach ($stmtCategoryAvg->fetchAll() as $row) {
    $categoryAverages[(int)$row['category_id']] = (float)$row['avg_amount'];
}

$stmtAnomalies = $pdo->prepare("
    SELECT e.description, e.amount, e.date, e.category_id, c.name AS category_name, p.name AS pocket_name
    FROM expenses e
    LEFT JOIN categories c ON e.category_id = c.id
    LEFT JOIN pockets p ON e.pocket_id = p.id
    WHERE e.user_id = ?
      AND e.date BETWEEN ? AND ?
      " . ($selectedPocketId !== null ? "AND e.pocket_id = ?" : "") . "
    ORDER BY e.amount DESC
    LIMIT 20
");
$anomalyParams = [$user_id, $start_date, $end_date];
if ($selectedPocketId !== null) $anomalyParams[] = $selectedPocketId;
$stmtAnomalies->execute($anomalyParams);
$anomalies = [];
foreach ($stmtAnomalies->fetchAll() as $row) {
    $categoryAvg = $categoryAverages[(int)$row['category_id']] ?? 0;
    $threshold = max($dailyAverage * 2, $categoryAvg * 1.8, 100000);
    if ((float)$row['amount'] >= $threshold) {
        $anomalies[] = $row;
    }
    if (count($anomalies) >= 5) break;
}
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Laporan Keuangan</h1>
        <p class="text-slate-500 text-sm">
            Analisis periode <span class="font-semibold text-slate-700"><?= $display_period ?></span> 
            (Cutoff Tgl: <?= $cutoffDay ?>)
        </p>
    </div>
    
    <div class="flex gap-2">
        <a href="profile.php" class="bg-white p-2 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50" title="Atur Cutoff">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </a>
        
        <form method="GET" class="bg-white p-1.5 rounded-lg border border-slate-300 flex items-center shadow-sm">
            <label class="px-3 text-xs font-semibold text-slate-500 uppercase">Periode:</label>
            <input type="month" name="month" value="<?= $selected_month ?>" 
                   class="text-sm font-medium text-slate-700 outline-none bg-transparent cursor-pointer"
                   onchange="this.form.submit()">
            <input type="hidden" name="pocket_id" value="<?= $selectedPocketId === null ? 'all' : (int)$selectedPocketId ?>">
        </form>

        <form method="GET" class="bg-white p-1.5 rounded-lg border border-slate-300 flex items-center shadow-sm">
            <label class="px-3 text-xs font-semibold text-slate-500 uppercase">Pocket:</label>
            <select name="pocket_id" onchange="this.form.submit()" class="text-sm font-medium text-slate-700 outline-none bg-transparent cursor-pointer">
                <option value="all" <?= $selectedPocketId === null ? 'selected' : '' ?>>Semua</option>
                <?php foreach ($pockets as $pocket): ?>
                    <option value="<?= (int)$pocket['id'] ?>" <?= (int)$selectedPocketId === (int)$pocket['id'] ? 'selected' : '' ?>><?= e($pocket['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="month" value="<?= e($selected_month) ?>">
        </form>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs text-slate-500 font-semibold uppercase mb-1">Total Pengeluaran</p>
        <h3 class="text-2xl font-bold text-slate-800 mb-2"><?= formatRupiah($grandTotal) ?></h3>
        <div class="flex items-center gap-2 text-xs">
            <?php if ($lastMonthTotal == 0): ?>
                <span class="text-slate-400">vs periode lalu (Data kosong)</span>
            <?php else: ?>
                <?php if ($percentageChange > 0): ?>
                    <span class="text-red-600 bg-red-50 px-1.5 py-0.5 rounded font-medium">Naik <?= number_format($percentageChange, 1) ?>%</span>
                <?php else: ?>
                    <span class="text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded font-medium">Turun <?= number_format(abs($percentageChange), 1) ?>%</span>
                <?php endif; ?>
                <span class="text-slate-400 ml-1">vs periode lalu</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs text-slate-500 font-semibold uppercase mb-1">Rata-rata Harian</p>
        <h3 class="text-2xl font-bold text-slate-800 mb-2"><?= formatRupiah($dailyAverage) ?></h3>
        <p class="text-xs text-slate-400">Selama <?= $daysPassed ?> hari berjalan</p>
    </div>
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <p class="text-xs text-slate-500 font-semibold uppercase mb-1">Boros Di Kategori</p>
        <?php if (!empty($categories)): ?>
            <h3 class="text-2xl font-bold text-slate-800 truncate mb-2"><?= htmlspecialchars($categories[0]['name']) ?></h3>
            <p class="text-xs text-slate-400">Sebesar <?= formatRupiah($categories[0]['total']) ?></p>
        <?php else: ?>
            <h3 class="text-2xl font-bold text-slate-800">-</h3>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-bold text-slate-800">Budget Health</h3>
            <span class="text-xs text-slate-400">Pocket aktif</span>
        </div>
        <div class="space-y-4">
            <?php if (empty($budgetHealth)): ?>
                <p class="text-sm text-slate-400">Belum ada pocket aktif.</p>
            <?php endif; ?>
            <?php foreach ($budgetHealth as $row): ?>
                <?php
                    $budgetActive = !empty($row['budget_enabled']) && (float)$row['budget_amount'] > 0;
                    $usedPercent = $budgetActive ? min(100, ((float)$row['total'] / (float)$row['budget_amount']) * 100) : 0;
                    $healthLabel = !$budgetActive ? 'Limit Off' : ($usedPercent >= 100 ? 'Lewat' : ($usedPercent >= 80 ? 'Waspada' : 'Aman'));
                    $healthClass = !$budgetActive ? 'bg-slate-50 text-slate-500 border-slate-200' : ($usedPercent >= 100 ? 'bg-red-50 text-red-700 border-red-200' : ($usedPercent >= 80 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'));
                ?>
                <div>
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <span class="text-sm font-medium text-slate-700 truncate"><?= e($row['name']) ?></span>
                        <span class="px-2 py-0.5 rounded-full text-xs border <?= $healthClass ?>"><?= $healthLabel ?></span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full <?= $usedPercent >= 100 ? 'bg-red-500' : ($usedPercent >= 80 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width: <?= e($usedPercent) ?>%"></div>
                    </div>
                    <p class="text-xs text-slate-400 mt-1"><?= formatRupiah($row['total']) ?><?= $budgetActive ? ' / ' . formatRupiah($row['budget_amount']) : '' ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="font-bold text-slate-800 mb-5">Analisis Per Pocket</h3>
        <div class="space-y-4">
            <?php foreach ($pocketCompare as $row): ?>
                <?php
                    $delta = (float)$row['current_total'] - (float)$row['previous_total'];
                    $deltaPercent = (float)$row['previous_total'] > 0 ? ($delta / (float)$row['previous_total']) * 100 : null;
                ?>
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 truncate"><?= e($row['name']) ?></p>
                        <p class="text-xs <?= $delta > 0 ? 'text-red-500' : ($delta < 0 ? 'text-emerald-600' : 'text-slate-400') ?>">
                            <?= $delta >= 0 ? '+' : '' ?><?= formatRupiah($delta) ?><?= $deltaPercent !== null ? ' (' . number_format($deltaPercent, 1) . '%)' : '' ?>
                        </p>
                    </div>
                    <p class="text-sm font-bold text-slate-800 whitespace-nowrap"><?= formatRupiah($row['current_total']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="font-bold text-slate-800 mb-5">Proyeksi Periode</h3>
        <p class="text-xs text-slate-500 font-semibold uppercase mb-1">Estimasi akhir periode</p>
        <h3 class="text-2xl font-bold text-slate-800 mb-2"><?= formatRupiah($projectedTotal) ?></h3>
        <p class="text-sm text-slate-500 mb-4">Berdasarkan rata-rata <?= formatRupiah($dailyAverage) ?> per hari.</p>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
                <p class="text-xs text-slate-400">Hari berjalan</p>
                <p class="font-bold text-slate-700"><?= $daysPassed ?> / <?= $totalDaysInPeriod ?></p>
            </div>
            <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
                <p class="text-xs text-slate-400">Sisa hari</p>
                <p class="font-bold text-slate-700"><?= max(0, $totalDaysInPeriod - $daysPassed) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="font-bold text-slate-800 mb-5">Kategori Naik/Turun</h3>
        <div class="space-y-4">
            <?php if (empty($categoryCompare)): ?>
                <p class="text-sm text-slate-400">Belum ada data pembanding.</p>
            <?php endif; ?>
            <?php foreach ($categoryCompare as $row): ?>
                <?php $delta = (float)$row['current_total'] - (float)$row['previous_total']; ?>
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 truncate"><?= e($row['name']) ?></p>
                        <p class="text-xs text-slate-400">Periode lalu: <?= formatRupiah($row['previous_total']) ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-slate-800"><?= formatRupiah($row['current_total']) ?></p>
                        <p class="text-xs <?= $delta > 0 ? 'text-red-500' : ($delta < 0 ? 'text-emerald-600' : 'text-slate-400') ?>"><?= $delta >= 0 ? '+' : '' ?><?= formatRupiah($delta) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <h3 class="font-bold text-slate-800 mb-5">Transaksi Anomali</h3>
        <div class="space-y-4">
            <?php if (empty($anomalies)): ?>
                <p class="text-sm text-slate-400">Tidak ada transaksi yang tampak tidak biasa pada periode ini.</p>
            <?php endif; ?>
            <?php foreach ($anomalies as $row): ?>
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-700 truncate"><?= e($row['description']) ?></p>
                        <p class="text-xs text-slate-400"><?= date('d M Y', strtotime($row['date'])) ?> &bull; <?= e($row['category_name']) ?><?= $row['pocket_name'] ? ' &bull; ' . e($row['pocket_name']) : '' ?></p>
                    </div>
                    <p class="text-sm font-bold text-red-600 whitespace-nowrap"><?= formatRupiah($row['amount']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
    <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-blue-600"><path d="M15.5 2A1.5 1.5 0 0014 3.5v8a1.5 1.5 0 001.5 1.5h1.5A1.5 1.5 0 0018.5 11.5v-8A1.5 1.5 0 0017 2h-1.5zM9 9a1.5 1.5 0 00-1.5 1.5v2.5A1.5 1.5 0 009 14.5h1.5a1.5 1.5 0 001.5-1.5v-2.5A1.5 1.5 0 0010.5 9H9zM3.5 10A1.5 1.5 0 002 11.5v1.5A1.5 1.5 0 003.5 14.5h1.5A1.5 1.5 0 006.5 13v-1.5A1.5 1.5 0 005 10H3.5z" /></svg>
        Tren Pengeluaran 6 Bulan Terakhir
    </h3>
    
    <?php if (empty($monthlyData)): ?>
        <p class="text-center text-slate-400 py-10 text-sm">Belum ada cukup data historis.</p>
    <?php else: ?>
        <div class="h-48 flex items-end justify-around gap-2 px-2 border-b border-slate-100 pb-1">
            <?php foreach ($monthlyData as $m): 
                $heightPercent = $maxMonthly > 0 ? ($m['total'] / $maxMonthly) * 100 : 0;
                $isCurrent = $m['mo'] == $selected_month;
            ?>
            <div class="flex flex-col justify-end items-center flex-1 h-full group relative">
                <div class="opacity-0 group-hover:opacity-100 absolute bottom-full mb-2 bg-slate-800 text-white text-[10px] px-2 py-1 rounded transition pointer-events-none whitespace-nowrap z-10">
                    <?= formatRupiah($m['total']) ?>
                </div>
                <div class="w-full max-w-[40px] rounded-t-sm transition-all duration-500 hover:opacity-80 <?= $isCurrent ? 'bg-blue-600' : 'bg-blue-300' ?>" 
                     style="height: <?= max(4, $heightPercent) ?>%;"></div>
                
                <span class="text-xs font-medium text-slate-500 mt-2"><?= $m['month_name'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6 relative">
        <h3 class="font-bold text-slate-800 mb-6 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 text-indigo-500"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zm6-4a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zm6-3a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z" /></svg>
            Tren Harian
        </h3>
        
        <div class="relative w-full h-80">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="font-bold text-slate-800 mb-6">Breakdown Kategori</h3>
        <?php if (empty($categories)): ?>
            <p class="text-slate-400 text-sm text-center py-8">Belum ada data.</p>
        <?php else: ?>
            <div class="space-y-5 overflow-y-auto max-h-[300px] pr-2 custom-scrollbar">
                <?php foreach ($categories as $row): 
                    $percent = $grandTotal > 0 ? ($row['total'] / $grandTotal) * 100 : 0;
                ?>
                <div>
                    <div class="flex justify-between items-end mb-1">
                        <span class="text-sm font-medium text-slate-700"><?= htmlspecialchars($row['name']) ?></span>
                        <div class="text-right">
                            <span class="text-sm font-bold text-slate-800"><?= formatRupiah($row['total']) ?></span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: <?= $percent ?>%"></div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1 text-right"><?= number_format($percent, 1) ?>% dari total</p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-bold text-slate-800">5 Pengeluaran Terbesar (Periode Ini)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-700 font-semibold uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">Tanggal</th>
                    <th class="px-6 py-3">Keterangan</th>
                    <th class="px-6 py-3 text-right">Nominal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($topExpenses)): ?>
                    <tr><td colspan="3" class="px-6 py-4 text-center text-slate-400">Data kosong</td></tr>
                <?php else: ?>
                    <?php foreach ($topExpenses as $row): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3 whitespace-nowrap"><?= date('d M Y', strtotime($row['date'])) ?></td>
                        <td class="px-6 py-3 font-medium text-slate-800"><?= htmlspecialchars($row['description']) ?></td>
                        <td class="px-6 py-3 text-right font-bold text-slate-700"><?= formatRupiah($row['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const chartCanvas = document.getElementById('dailyChart');
    if (chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
        const labels = <?= json_encode($chartLabels) ?>;
        const dataValues = <?= json_encode($chartData) ?>;

        let gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
        gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Pengeluaran',
                    data: dataValues,
                    borderColor: '#4f46e5',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    spanGaps: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#4f46e5',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        filter: function(tooltipItem) {
                            return tooltipItem.raw !== null;
                        },
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                let value = context.raw;
                                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9', borderDash: [5, 5] },
                        ticks: {
                            font: { size: 10 },
                            color: '#94a3b8',
                            callback: function(value) {
                                if(value >= 1000000) return (value/1000000) + ' Jt';
                                if(value >= 1000) return (value/1000) + ' Rb';
                                return value;
                            }
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 10 },
                            color: '#94a3b8',
                            autoSkip: true,
                            maxTicksLimit: 12
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
