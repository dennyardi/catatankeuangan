<?php
require_once 'includes/header.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT);
    $pocket_id = getSelectedPocketId($pdo, $user_id, $_POST['pocket_id'] ?? null);
    $description = trim($_POST['description']);
    $date = $_POST['date'];

    if ($amount && $category_id && $pocket_id && $date) {
        try {
            if (!categoryIsAvailableForPocket($pdo, $category_id, $user_id, $pocket_id)) {
                throw new InvalidArgumentException('Kategori tidak tersedia untuk pocket ini.');
            }
            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, pocket_id, amount, category_id, description, date, source) VALUES (?, ?, ?, ?, ?, ?, 'web')");
            $stmt->execute([$user_id, $pocket_id, $amount, $category_id, $description, $date]);
            $message = "Transaksi berhasil dicatat.";
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem.";
        }
    } else {
        $error = "Mohon lengkapi seluruh data wajib.";
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
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-slate-800">Catat Pengeluaran</h1>
        <p class="text-slate-500 text-sm">Pastikan setiap sen tercatat untuk analisis yang akurat.</p>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm border border-emerald-200">
            <i data-lucide="check-circle" class="w-4 h-4"></i> <?= e($message) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm border border-red-200">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i> <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <form action="" method="POST" class="space-y-5">
            <?= csrfField() ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Tanggal Transaksi</label>
                    <input type="date" name="date" value="<?= date('Y-m-d') ?>" required
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Nominal (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-2.5 text-slate-400">Rp</span>
                        <input type="number" name="amount" placeholder="0" required
                            class="w-full pl-12 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800 font-medium">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Pocket</label>
                <div class="relative">
                    <select name="pocket_id" id="pocket_id" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800 appearance-none bg-white">
                        <?php foreach ($pockets as $pocket): ?>
                            <option value="<?= (int)$pocket['id'] ?>"><?= e($pocket['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-4 top-3.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Kategori</label>
                <div class="relative">
                    <select name="category_id" id="category_id" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800 appearance-none bg-white">
                        <option value="" disabled selected>Pilih Kategori Pengeluaran</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" data-pocket-id="<?= e($cat['pocket_id'] ?? '') ?>"><?= e($cat['name']) ?><?= !empty($cat['pocket_id']) ? ' - ' . e($cat['pocket_name']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <i data-lucide="chevron-down" class="absolute right-4 top-3.5 w-4 h-4 text-slate-400 pointer-events-none"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Keterangan / Detail</label>
                <textarea name="description" rows="3" placeholder="Contoh: Makan siang di warteg, Bensin pertamax..."
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800"></textarea>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-lg transition shadow-md hover:shadow-lg flex justify-center items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const pocketSelect = document.getElementById('pocket_id');
    const categorySelect = document.getElementById('category_id');
    if (!pocketSelect || !categorySelect) return;

    function filterCategories() {
        const selectedPocket = pocketSelect.value;
        Array.from(categorySelect.options).forEach(function (option) {
            const scope = option.getAttribute('data-pocket-id');
            option.hidden = scope && scope !== selectedPocket;
        });
        if (categorySelect.selectedOptions.length && categorySelect.selectedOptions[0].hidden) {
            categorySelect.value = '';
        }
    }

    pocketSelect.addEventListener('change', filterCategories);
    filterCategories();
});
</script>
<?php require_once 'includes/footer.php'; ?>
