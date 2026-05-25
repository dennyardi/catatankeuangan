<?php
require_once 'includes/header.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// --- 1. CONFIGURATION & INPUTS ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; 
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selectedPocketId = getSelectedPocketId($pdo, $user_id, $_GET['pocket_id'] ?? 'all', true);
$pockets = getUserPockets($pdo, $user_id, true);

$allowed_limits = [10, 25, 50, 100];
if (!in_array($limit, $allowed_limits)) $limit = 10;
if ($page < 1) $page = 1;

// --- 2. QUERY DATA ---
// Hitung Total Data (untuk pagination)
$sql_count = "SELECT COUNT(*) as total 
              FROM expenses e 
              LEFT JOIN categories c ON e.category_id = c.id 
              WHERE e.user_id = ?";
$params = [$user_id];

if ($selectedPocketId !== null) {
    $sql_count .= " AND e.pocket_id = ?";
    $params[] = $selectedPocketId;
}

if ($search) {
    $sql_count .= " AND (e.description LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmtCount = $pdo->prepare($sql_count);
$stmtCount->execute($params);
$total_records = $stmtCount->fetch()['total'];
$total_pages = ceil($total_records / $limit);

if ($page > $total_pages && $total_records > 0) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Ambil Data Halaman Ini
$sql_data = "SELECT e.*, c.name as category_name, p.name as pocket_name 
             FROM expenses e 
             LEFT JOIN categories c ON e.category_id = c.id 
             LEFT JOIN pockets p ON e.pocket_id = p.id
             WHERE e.user_id = ?";

if ($selectedPocketId !== null) $sql_data .= " AND e.pocket_id = ?";
if ($search) $sql_data .= " AND (e.description LIKE ? OR c.name LIKE ?)";

$sql_data .= " ORDER BY e.date DESC, e.id DESC LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql_data);
$stmt->execute($params);
$expenses = $stmt->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Riwayat Transaksi</h1>
    <p class="text-slate-500 text-sm">Kelola dan pantau arus kas pengeluaran Anda.</p>
</div>

<div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
    
    <form method="GET" class="w-full md:w-auto relative">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
            </svg>
        </span>
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Cari transaksi..." 
            class="w-full md:w-64 pl-10 pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition"
            onchange="this.form.submit()">
        <input type="hidden" name="limit" value="<?= $limit ?>">
        <input type="hidden" name="pocket_id" value="<?= $selectedPocketId === null ? 'all' : (int)$selectedPocketId ?>">
    </form>

    <form method="GET" class="w-full md:w-auto">
        <select name="pocket_id" onchange="this.form.submit()" class="w-full md:w-56 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
            <option value="all" <?= $selectedPocketId === null ? 'selected' : '' ?>>Semua Pocket</option>
            <?php foreach ($pockets as $pocket): ?>
                <option value="<?= (int)$pocket['id'] ?>" <?= (int)$selectedPocketId === (int)$pocket['id'] ? 'selected' : '' ?>><?= e($pocket['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="search" value="<?= e($search) ?>">
        <input type="hidden" name="limit" value="<?= $limit ?>">
    </form>

    <a href="input.php" class="w-full md:w-auto bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center justify-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-slate-500">
            <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
        </svg>
        Tambah Data
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col min-h-[400px]">
    <div class="overflow-x-auto flex-grow">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-100 uppercase tracking-wider text-xs">
                <tr>
                    <th class="px-6 py-4">Tanggal</th>
                    <th class="px-6 py-4">Keterangan</th>
                    <th class="px-6 py-4">Kategori</th>
                    <th class="px-6 py-4">Pocket</th>
                    <th class="px-6 py-4 text-right">Nominal</th>
                    <th class="px-6 py-4 text-center">Opsi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400 flex flex-col items-center justify-center">
                            <p>Tidak ada data ditemukan.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($expenses as $row): ?>
                    <tr class="hover:bg-slate-50 transition group">
                        <td class="px-6 py-4 whitespace-nowrap text-slate-800">
                            <div class="font-medium"><?= date('d M Y', strtotime($row['date'])) ?></div>
                            <div class="text-xs text-slate-400"><?= date('H:i', strtotime($row['created_at'])) ?></div>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-900">
                            <?= e($row['description']) ?>
                            <?php if($row['source'] == 'wa'): ?>
                                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800 border border-emerald-200">WA</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                <?= e($row['category_name']) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                <?= e($row['pocket_name'] ?? 'Pocket lama') ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-semibold text-slate-700">
                            <?= formatRupiah($row['amount']) ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="edit_expense.php?id=<?= $row['id'] ?>" class="text-slate-400 hover:text-indigo-600 transition p-1.5 hover:bg-indigo-50 rounded-md" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" />
                                        <path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" />
                                    </svg>
                                </a>
                                <form method="POST" action="delete_expense.php" onsubmit="return confirm('Hapus transaksi ini? Data tidak bisa dikembalikan.')" class="inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" class="text-slate-400 hover:text-red-600 transition p-1.5 hover:bg-red-50 rounded-md" title="Hapus">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                                    </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex flex-col md:flex-row items-center justify-between gap-4">
        
        <form method="GET" class="flex items-center gap-2 text-xs text-slate-500">
            <span>Tampilkan</span>
            <select name="limit" onchange="this.form.submit()" class="px-2 py-1 border border-slate-300 rounded text-xs focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition bg-white text-slate-700 cursor-pointer">
                <?php foreach ($allowed_limits as $val): ?>
                    <option value="<?= $val ?>" <?= $limit == $val ? 'selected' : '' ?>><?= $val ?></option>
                <?php endforeach; ?>
            </select>
            <span>dari <strong><?= $total_records ?></strong> data</span>
            <input type="hidden" name="search" value="<?= e($search) ?>">
            <input type="hidden" name="pocket_id" value="<?= $selectedPocketId === null ? 'all' : (int)$selectedPocketId ?>">
        </form>
        
        <?php if ($total_pages > 1): ?>
        <div class="flex gap-1">
            <a href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&pocket_id=<?= $selectedPocketId === null ? 'all' : (int)$selectedPocketId ?>" 
               class="px-3 py-1 rounded border border-slate-300 text-xs font-medium <?= $page <= 1 ? 'text-slate-300 pointer-events-none bg-slate-50' : 'text-slate-600 hover:bg-white hover:text-indigo-600 bg-white' ?>">
                Prev
            </a>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                    <a href="?page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&pocket_id=<?= $selectedPocketId === null ? 'all' : (int)$selectedPocketId ?>" 
                       class="px-3 py-1 rounded border text-xs font-medium transition <?= $i == $page ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?>">
                        <?= $i ?>
                    </a>
                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                    <span class="px-2 py-1 text-slate-400 text-xs">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <a href="?page=<?= min($total_pages, $page + 1) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&pocket_id=<?= $selectedPocketId === null ? 'all' : (int)$selectedPocketId ?>" 
               class="px-3 py-1 rounded border border-slate-300 text-xs font-medium <?= $page >= $total_pages ? 'text-slate-300 pointer-events-none bg-slate-50' : 'text-slate-600 hover:bg-white hover:text-indigo-600 bg-white' ?>">
                Next
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
