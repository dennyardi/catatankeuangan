<?php
require_once 'includes/header.php';
checkLogin();

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';
    $rule_id = filter_input(INPUT_POST, 'rule_id', FILTER_VALIDATE_INT);
    $keyword = strtolower(trim($_POST['keyword'] ?? ''));
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $pocket_id_raw = $_POST['pocket_id'] ?? '';
    $pocket_id = $pocket_id_raw === '' ? null : filter_var($pocket_id_raw, FILTER_VALIDATE_INT);
    $priority = filter_input(INPUT_POST, 'priority', FILTER_VALIDATE_INT);
    $category_name = trim($_POST['category_name'] ?? '');
    $managed_category_id = filter_input(INPUT_POST, 'managed_category_id', FILTER_VALIDATE_INT);
    $category_pocket_id_raw = $_POST['category_pocket_id'] ?? '';
    $category_pocket_id = $category_pocket_id_raw === '' ? null : filter_var($category_pocket_id_raw, FILTER_VALIDATE_INT);
    $category_budget_amount = normalizeMoneyInput($_POST['category_budget_amount'] ?? 0);
    $category_budget_enabled = isset($_POST['category_budget_enabled']) ? 1 : 0;
    if (!$priority || $priority < 1) $priority = 10;

    try {
        if ($action === 'create_category' || $action === 'update_category') {
            if ($category_name === '') {
                $error = 'Nama kategori wajib diisi.';
            } else {
                if ($category_pocket_id) {
                    $stmtPocket = $pdo->prepare("SELECT id FROM pockets WHERE id = ? AND user_id = ?");
                    $stmtPocket->execute([$category_pocket_id, $user_id]);
                    if (!$stmtPocket->fetchColumn()) {
                        $error = 'Pocket kategori tidak valid.';
                    }
                }
            }

            if (!$error && $action === 'create_category') {
                $stmt = $pdo->prepare("INSERT INTO categories (user_id, pocket_id, name, budget_amount, budget_enabled) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $category_pocket_id, $category_name, $category_budget_amount, $category_budget_enabled]);
                $message = 'Kategori baru berhasil ditambahkan.';
            } elseif (!$error && $action === 'update_category' && $managed_category_id) {
                $stmtCategory = $pdo->prepare("SELECT id, user_id FROM categories WHERE id = ? AND (user_id IS NULL OR user_id = ?)");
                $stmtCategory->execute([$managed_category_id, $user_id]);
                $existingCategory = $stmtCategory->fetch();

                if (!$existingCategory) {
                    $error = 'Kategori tidak ditemukan.';
                } else {
                    if ($existingCategory['user_id'] === null) {
                        $stmt = $pdo->prepare("UPDATE categories SET user_id = ?, name = ?, pocket_id = ?, budget_amount = ?, budget_enabled = ? WHERE id = ?");
                        $stmt->execute([$user_id, $category_name, $category_pocket_id, $category_budget_amount, $category_budget_enabled, $managed_category_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, pocket_id = ?, budget_amount = ?, budget_enabled = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$category_name, $category_pocket_id, $category_budget_amount, $category_budget_enabled, $managed_category_id, $user_id]);
                    }
                    $message = 'Kategori berhasil diperbarui.';
                }
            }
        }

        if (!$error && ($action === 'create' || $action === 'update') && $keyword === '') {
            $error = 'Keyword wajib diisi.';
        }

        if (($action === 'create' || $action === 'update') && !$category_id) {
            $error = 'Kategori wajib dipilih.';
        }

        if (!$error && $pocket_id) {
            $stmtPocket = $pdo->prepare("SELECT id FROM pockets WHERE id = ? AND user_id = ?");
            $stmtPocket->execute([$pocket_id, $user_id]);
            if (!$stmtPocket->fetchColumn()) {
                $error = 'Pocket tidak valid.';
            }
        }

        if (!$error && ($action === 'create' || $action === 'update')) {
            if (!categoryIsAvailableForPocket($pdo, $category_id, $user_id, $pocket_id)) {
                $error = 'Kategori tidak valid.';
            }
        }

        if (!$error && $action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO category_rules (user_id, pocket_id, category_id, keyword, priority, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $pocket_id, $category_id, $keyword, $priority]);
            $message = 'Rule kategori berhasil ditambahkan.';
        } elseif (!$error && $action === 'update' && $rule_id) {
            $stmt = $pdo->prepare("UPDATE category_rules SET pocket_id = ?, category_id = ?, keyword = ?, priority = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$pocket_id, $category_id, $keyword, $priority, $rule_id, $user_id]);
            $message = 'Rule kategori berhasil diperbarui.';
        } elseif ($action === 'toggle' && $rule_id) {
            $stmt = $pdo->prepare("SELECT is_active FROM category_rules WHERE id = ? AND user_id = ?");
            $stmt->execute([$rule_id, $user_id]);
            $current = $stmt->fetchColumn();
            if ($current !== false) {
                $newStatus = ((int)$current === 1) ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE category_rules SET is_active = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$newStatus, $rule_id, $user_id]);
                $message = $newStatus ? 'Rule diaktifkan kembali.' : 'Rule dinonaktifkan.';
            }
        } elseif ($action === 'delete' && $rule_id) {
            $stmt = $pdo->prepare("DELETE FROM category_rules WHERE id = ? AND user_id = ?");
            $stmt->execute([$rule_id, $user_id]);
            $message = 'Rule kategori dihapus.';
        } elseif ($action === 'delete_category' && $managed_category_id) {
            $stmtCategory = $pdo->prepare("SELECT id, user_id FROM categories WHERE id = ? AND user_id = ?");
            $stmtCategory->execute([$managed_category_id, $user_id]);
            if (!$stmtCategory->fetch()) {
                $error = 'Kategori tidak ditemukan atau kategori bawaan tidak bisa dihapus.';
            } else {
                $stmtExpense = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category_id = ? AND user_id = ?");
                $stmtExpense->execute([$managed_category_id, $user_id]);
                if ((int)$stmtExpense->fetchColumn() > 0) {
                    $error = 'Kategori sudah dipakai transaksi, jadi tidak bisa dihapus.';
                } else {
                    $pdo->beginTransaction();
                    $stmtRuleDelete = $pdo->prepare("DELETE FROM category_rules WHERE category_id = ? AND user_id = ?");
                    $stmtRuleDelete->execute([$managed_category_id, $user_id]);
                    $stmtDelete = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
                    $stmtDelete->execute([$managed_category_id, $user_id]);
                    $pdo->commit();
                    $message = 'Kategori berhasil dihapus.';
                }
            }
        } elseif ($action === 'seed_default_rules') {
            $inserted = seedDefaultCategoryRules($pdo, $user_id);
            $message = $inserted > 0 ? "Rule awal berhasil ditambahkan: $inserted keyword." : 'Semua rule awal sudah tersedia.';
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = in_array($action, ['create_category', 'update_category', 'delete_category'], true) ? 'Gagal menyimpan kategori.' : 'Gagal menyimpan rule kategori.';
    }
}

$pockets = getUserPockets($pdo, $user_id, true);
$stmtCategories = $pdo->prepare("
    SELECT c.*, p.name AS pocket_name
    FROM categories c
    LEFT JOIN pockets p ON c.pocket_id = p.id
    WHERE c.user_id IS NULL OR c.user_id = ?
    ORDER BY c.name ASC
");
$stmtCategories->execute([$user_id]);
$categories = $stmtCategories->fetchAll();

$stmtRules = $pdo->prepare("
    SELECT r.*, c.name AS category_name, p.name AS pocket_name
    FROM category_rules r
    JOIN categories c ON r.category_id = c.id
    LEFT JOIN pockets p ON r.pocket_id = p.id
    WHERE r.user_id = ?
    ORDER BY r.is_active DESC, r.priority ASC, r.keyword ASC
");
$stmtRules->execute([$user_id]);
$rules = $stmtRules->fetchAll();

$stmtCategoryOverview = $pdo->prepare("
    SELECT c.id, c.user_id, c.name, c.pocket_id, c.budget_amount, c.budget_enabled, p_scope.name AS scope_name,
           COUNT(r.id) AS rule_count,
           GROUP_CONCAT(
               CASE
                   WHEN r.id IS NULL THEN NULL
                   WHEN r.pocket_id IS NULL THEN CONCAT(r.keyword, ' (semua)')
                   ELSE CONCAT(r.keyword, ' (', p.name, ')')
               END
               ORDER BY r.priority ASC, r.keyword ASC SEPARATOR ', '
           ) AS keywords
    FROM categories c
    LEFT JOIN category_rules r ON r.category_id = c.id AND r.user_id = ?
    LEFT JOIN pockets p ON r.pocket_id = p.id
    LEFT JOIN pockets p_scope ON c.pocket_id = p_scope.id
    WHERE c.user_id IS NULL OR c.user_id = ?
    GROUP BY c.id, c.user_id, c.name, c.pocket_id, c.budget_amount, c.budget_enabled, p_scope.name
    ORDER BY c.name ASC
");
$stmtCategoryOverview->execute([$user_id, $user_id]);
$categoryOverview = $stmtCategoryOverview->fetchAll();

$allowedPageSizes = [5, 10];
$catSearch = trim($_GET['cat_search'] ?? '');
$ruleSearch = trim($_GET['rule_search'] ?? '');
$catLimitRequested = (int)($_GET['cat_limit'] ?? 5);
$ruleLimitRequested = (int)($_GET['rule_limit'] ?? 5);
$catLimit = in_array($catLimitRequested, $allowedPageSizes, true) ? $catLimitRequested : 5;
$ruleLimit = in_array($ruleLimitRequested, $allowedPageSizes, true) ? $ruleLimitRequested : 5;
$catPage = max(1, (int)($_GET['cat_page'] ?? 1));
$rulePage = max(1, (int)($_GET['rule_page'] ?? 1));

if ($catSearch !== '') {
    $categoryOverview = array_values(array_filter($categoryOverview, function ($category) use ($catSearch) {
        $haystack = strtolower(($category['name'] ?? '') . ' ' . ($category['scope_name'] ?? 'Semua Pocket') . ' ' . ($category['keywords'] ?? ''));
        return strpos($haystack, strtolower($catSearch)) !== false;
    }));
}

if ($ruleSearch !== '') {
    $rules = array_values(array_filter($rules, function ($rule) use ($ruleSearch) {
        $haystack = strtolower(($rule['keyword'] ?? '') . ' ' . ($rule['category_name'] ?? '') . ' ' . ($rule['pocket_name'] ?? 'Semua Pocket'));
        return strpos($haystack, strtolower($ruleSearch)) !== false;
    }));
}

$catTotal = count($categoryOverview);
$ruleTotal = count($rules);
$catTotalPages = max(1, (int)ceil($catTotal / $catLimit));
$ruleTotalPages = max(1, (int)ceil($ruleTotal / $ruleLimit));
$catPage = min($catPage, $catTotalPages);
$rulePage = min($rulePage, $ruleTotalPages);
$categoryOverviewPage = array_slice($categoryOverview, ($catPage - 1) * $catLimit, $catLimit);
$rulesPage = array_slice($rules, ($rulePage - 1) * $ruleLimit, $ruleLimit);

function queryUrl($overrides) {
    return '?' . http_build_query(array_merge($_GET, $overrides));
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Rule Kategori</h1>
    <p class="text-slate-500 text-sm">Atur keyword agar transaksi WhatsApp otomatis masuk kategori yang tepat.</p>
</div>

<?php if ($message): ?>
    <div class="mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg text-sm border border-emerald-200"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-lg text-sm border border-red-200"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Tambah Kategori</h2>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_category">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Nama Kategori</label>
                <input type="text" name="category_name" required placeholder="Contoh: Belanja Dapur"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Berlaku Untuk</label>
                <select name="category_pocket_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
                    <option value="">Semua Pocket</option>
                    <?php foreach ($pockets as $pocket): ?>
                        <option value="<?= (int)$pocket['id'] ?>"><?= e($pocket['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Limit Kategori</label>
                <input type="text" name="category_budget_amount" placeholder="Contoh: 750000"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
            </div>
            <label class="flex items-center justify-between gap-3 text-sm text-slate-700">
                <span>Aktifkan limit kategori</span>
                <span class="relative inline-flex">
                    <input type="checkbox" name="category_budget_enabled" value="1" class="toggle-input sr-only">
                    <span class="toggle-switch"></span>
                </span>
            </label>
            <button type="submit" class="w-full bg-slate-900 hover:bg-slate-700 text-white font-medium py-2.5 rounded-lg transition">Simpan Kategori</button>
        </form>
    </div>

    <div id="kategori-keyword" class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden scroll-mt-6">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-800">Kategori & Keyword</h2>
                <span class="text-xs text-slate-500"><?= $catTotal ?> data</span>
            </div>
            <form method="GET" action="#kategori-keyword" class="auto-filter-form">
                <input type="text" name="cat_search" value="<?= e($catSearch) ?>" placeholder="Cari kategori, scope, atau keyword..."
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                <input type="hidden" name="cat_page" value="1">
                <input type="hidden" name="rule_search" value="<?= e($ruleSearch) ?>">
                <input type="hidden" name="rule_limit" value="<?= (int)$ruleLimit ?>">
                <input type="hidden" name="rule_page" value="<?= (int)$rulePage ?>">
            </form>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($categoryOverviewPage)): ?>
                <div class="p-8 text-center text-sm text-slate-400">Tidak ada kategori ditemukan.</div>
            <?php endif; ?>

            <?php foreach ($categoryOverviewPage as $category): ?>
                <form method="POST" class="p-5 space-y-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="managed_category_id" value="<?= (int)$category['id'] ?>">

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-800"><?= e($category['name']) ?></h3>
                            <span class="px-2 py-1 rounded-full text-xs border bg-slate-50 text-slate-600 border-slate-200">
                                <?= $category['pocket_id'] ? e($category['scope_name']) : 'Semua Pocket' ?>
                            </span>
                            <span class="px-2 py-1 rounded-full text-xs border <?= !empty($category['budget_enabled']) ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-slate-50 text-slate-500 border-slate-200' ?>">
                                <?= !empty($category['budget_enabled']) ? 'Limit On' : 'Limit Off' ?>
                            </span>
                            <span class="px-2 py-1 rounded-full text-xs border bg-slate-50 text-slate-500 border-slate-200">
                                <?= (int)$category['rule_count'] ?> rule
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="px-3 py-2 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-sm transition">Update</button>
                            <?php if (!empty($category['user_id'])): ?>
                                <button type="submit" name="action" value="delete_category" onclick="return confirm('Hapus kategori ini? Rule kategori ikut terhapus.')" class="px-3 py-2 bg-white border border-red-200 hover:bg-red-50 text-red-600 rounded-lg text-sm transition">Hapus</button>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded-full text-xs border bg-slate-50 text-slate-500 border-slate-200">Bawaan</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Kategori</label>
                            <input type="text" name="category_name" value="<?= e($category['name']) ?>"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Scope</label>
                            <select name="category_pocket_id"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
                                <option value="">Semua Pocket</option>
                                <?php foreach ($pockets as $pocket): ?>
                                    <option value="<?= (int)$pocket['id'] ?>" <?= (int)$pocket['id'] === (int)$category['pocket_id'] ? 'selected' : '' ?>><?= e($pocket['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grid grid-cols-[1fr_auto] gap-3 items-end">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Limit</label>
                                <input type="text" name="category_budget_amount" value="<?= e(number_format((float)$category['budget_amount'], 0, ',', '.')) ?>"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                            </div>
                            <label class="flex flex-col items-center gap-1 text-xs text-slate-600 pb-0.5">
                                <span>Limit</span>
                                <span class="relative inline-flex">
                                    <input type="checkbox" name="category_budget_enabled" value="1" <?= !empty($category['budget_enabled']) ? 'checked' : '' ?> class="toggle-input sr-only">
                                    <span class="toggle-switch"></span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Keyword Aktif</label>
                        <p class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-500 min-h-[38px] break-words"><?= $category['keywords'] ? e($category['keywords']) : 'Belum ada keyword' ?></p>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <form method="GET" action="#kategori-keyword" class="flex items-center gap-2 text-xs text-slate-500">
                    <span>Tampilkan</span>
                    <select name="cat_limit" class="auto-submit-select px-2 py-1 border border-slate-300 rounded text-xs bg-white text-slate-700">
                        <?php foreach ($allowedPageSizes as $size): ?>
                            <option value="<?= $size ?>" <?= $catLimit === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>data · Halaman <?= $catPage ?> dari <?= $catTotalPages ?></span>
                    <input type="hidden" name="cat_search" value="<?= e($catSearch) ?>">
                    <input type="hidden" name="cat_page" value="1">
                    <input type="hidden" name="rule_search" value="<?= e($ruleSearch) ?>">
                    <input type="hidden" name="rule_limit" value="<?= (int)$ruleLimit ?>">
                    <input type="hidden" name="rule_page" value="<?= (int)$rulePage ?>">
                </form>
                <?php if ($catTotalPages > 1): ?>
                <div class="flex flex-wrap gap-2">
                    <a href="<?= e(queryUrl(['cat_page' => max(1, $catPage - 1)])) ?>#kategori-keyword" class="px-3 py-1.5 rounded border border-slate-300 text-xs <?= $catPage <= 1 ? 'pointer-events-none text-slate-300 bg-slate-50' : 'text-slate-600 bg-white hover:bg-slate-50' ?>">Prev</a>
                    <?php for ($i = 1; $i <= $catTotalPages; $i++): ?>
                        <a href="<?= e(queryUrl(['cat_page' => $i])) ?>#kategori-keyword" class="px-3 py-1.5 rounded border text-xs <?= $i === $catPage ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a href="<?= e(queryUrl(['cat_page' => min($catTotalPages, $catPage + 1)])) ?>#kategori-keyword" class="px-3 py-1.5 rounded border border-slate-300 text-xs <?= $catPage >= $catTotalPages ? 'pointer-events-none text-slate-300 bg-slate-50' : 'text-slate-600 bg-white hover:bg-slate-50' ?>">Next</a>
                </div>
                <?php endif; ?>
            </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h2 class="text-base font-semibold text-slate-800">Tambah Rule</h2>
            <form method="POST">
                <?= csrfField() ?>
                <button type="submit" name="action" value="seed_default_rules" class="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-medium transition">
                    Isi Rule Awal
                </button>
            </form>
        </div>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Keyword</label>
                <input type="text" name="keyword" required placeholder="Contoh: beras"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Pocket</label>
                <select name="pocket_id" class="rule-pocket-select w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
                    <option value="">Semua Pocket</option>
                    <?php foreach ($pockets as $pocket): ?>
                        <option value="<?= (int)$pocket['id'] ?>"><?= e($pocket['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Kategori</label>
                <select name="category_id" required class="category-select w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
                    <option value="">Pilih kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category['id'] ?>" data-pocket-id="<?= e($category['pocket_id'] ?? '') ?>"><?= e($category['name']) ?> - <?= $category['pocket_id'] ? e($category['pocket_name']) : 'Semua Pocket' ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-400 mt-2">Jika kategori khusus pocket dipilih, pocket akan otomatis disesuaikan.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Prioritas</label>
                <input type="number" name="priority" value="10" min="1"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                <p class="text-xs text-slate-400 mt-2">Angka kecil diproses lebih dulu.</p>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition">Simpan Rule</button>
        </form>
    </div>

    <div id="daftar-rule" class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden scroll-mt-6">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-800">Daftar Rule</h2>
                <span class="text-xs text-slate-500"><?= $ruleTotal ?> data</span>
            </div>
            <form method="GET" action="#daftar-rule" class="auto-filter-form">
                <input type="text" name="rule_search" value="<?= e($ruleSearch) ?>" placeholder="Cari keyword, kategori, atau pocket..."
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                <input type="hidden" name="rule_page" value="1">
                <input type="hidden" name="cat_search" value="<?= e($catSearch) ?>">
                <input type="hidden" name="cat_limit" value="<?= (int)$catLimit ?>">
                <input type="hidden" name="cat_page" value="<?= (int)$catPage ?>">
            </form>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($rulesPage)): ?>
                <div class="p-8 text-center text-sm text-slate-400">Belum ada rule kategori.</div>
            <?php endif; ?>

            <?php foreach ($rulesPage as $rule): ?>
                <form method="POST" class="p-5 space-y-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="rule_id" value="<?= (int)$rule['id'] ?>">

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-800"><?= e($rule['keyword']) ?></h3>
                            <span class="px-2 py-1 rounded-full text-xs border <?= $rule['is_active'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200' ?>">
                                <?= $rule['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                            <span class="px-2 py-1 rounded-full text-xs border bg-slate-50 text-slate-500 border-slate-200"><?= e($rule['category_name']) ?></span>
                            <span class="px-2 py-1 rounded-full text-xs border bg-slate-50 text-slate-500 border-slate-200"><?= $rule['pocket_id'] ? e($rule['pocket_name']) : 'Semua Pocket' ?></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="px-3 py-2 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-sm transition">Update</button>
                            <button type="submit" name="action" value="toggle" class="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm transition"><?= $rule['is_active'] ? 'Off' : 'On' ?></button>
                            <button type="submit" name="action" value="delete" onclick="return confirm('Hapus rule ini?')" class="px-3 py-2 bg-white border border-red-200 hover:bg-red-50 text-red-600 rounded-lg text-sm transition">Hapus</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_120px] gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Keyword</label>
                            <input type="text" name="keyword" value="<?= e($rule['keyword']) ?>" required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Pocket</label>
                            <select name="pocket_id" class="rule-pocket-select w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
                                <option value="">Semua Pocket</option>
                                <?php foreach ($pockets as $pocket): ?>
                                    <option value="<?= (int)$pocket['id'] ?>" <?= (int)$pocket['id'] === (int)$rule['pocket_id'] ? 'selected' : '' ?>><?= e($pocket['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Kategori</label>
                            <select name="category_id" required class="category-select w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int)$category['id'] ?>" data-pocket-id="<?= e($category['pocket_id'] ?? '') ?>" <?= (int)$category['id'] === (int)$rule['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?> - <?= $category['pocket_id'] ? e($category['pocket_name']) : 'Semua Pocket' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Prioritas</label>
                            <input type="number" name="priority" value="<?= (int)$rule['priority'] ?>" min="1"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <form method="GET" action="#daftar-rule" class="flex items-center gap-2 text-xs text-slate-500">
                    <span>Tampilkan</span>
                    <select name="rule_limit" class="auto-submit-select px-2 py-1 border border-slate-300 rounded text-xs bg-white text-slate-700">
                        <?php foreach ($allowedPageSizes as $size): ?>
                            <option value="<?= $size ?>" <?= $ruleLimit === $size ? 'selected' : '' ?>><?= $size ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>data · Halaman <?= $rulePage ?> dari <?= $ruleTotalPages ?></span>
                    <input type="hidden" name="rule_search" value="<?= e($ruleSearch) ?>">
                    <input type="hidden" name="rule_page" value="1">
                    <input type="hidden" name="cat_search" value="<?= e($catSearch) ?>">
                    <input type="hidden" name="cat_limit" value="<?= (int)$catLimit ?>">
                    <input type="hidden" name="cat_page" value="<?= (int)$catPage ?>">
                </form>
                <?php if ($ruleTotalPages > 1): ?>
                <div class="flex flex-wrap gap-2">
                    <a href="<?= e(queryUrl(['rule_page' => max(1, $rulePage - 1)])) ?>#daftar-rule" class="px-3 py-1.5 rounded border border-slate-300 text-xs <?= $rulePage <= 1 ? 'pointer-events-none text-slate-300 bg-slate-50' : 'text-slate-600 bg-white hover:bg-slate-50' ?>">Prev</a>
                    <?php for ($i = 1; $i <= $ruleTotalPages; $i++): ?>
                        <a href="<?= e(queryUrl(['rule_page' => $i])) ?>#daftar-rule" class="px-3 py-1.5 rounded border text-xs <?= $i === $rulePage ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a href="<?= e(queryUrl(['rule_page' => min($ruleTotalPages, $rulePage + 1)])) ?>#daftar-rule" class="px-3 py-1.5 rounded border border-slate-300 text-xs <?= $rulePage >= $ruleTotalPages ? 'pointer-events-none text-slate-300 bg-slate-50' : 'text-slate-600 bg-white hover:bg-slate-50' ?>">Next</a>
                </div>
                <?php endif; ?>
            </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.auto-filter-form input[type="text"]').forEach(function (input) {
        let timer;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                input.form.submit();
            }, 500);
        });
    });

    document.querySelectorAll('.auto-submit-select').forEach(function (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    });

    document.querySelectorAll('form').forEach(function (form) {
        const pocketSelect = form.querySelector('.rule-pocket-select');
        const categorySelect = form.querySelector('.category-select');
        if (!pocketSelect || !categorySelect) return;

        function syncPocketFromCategory() {
            const selected = categorySelect.selectedOptions[0];
            if (!selected) return;

            const categoryPocket = selected.getAttribute('data-pocket-id');
            if (categoryPocket) {
                pocketSelect.value = categoryPocket;
            }
        }

        categorySelect.addEventListener('change', syncPocketFromCategory);
        syncPocketFromCategory();
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
