<?php
require_once 'includes/header.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
$message = '';
$error = '';

// 1. Ambil Data Lama
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $data = $stmt->fetch();

    if (!$data) {
        redirect('transactions.php'); // Data tidak ditemukan/bukan milik user
    }
} else {
    redirect('transactions.php');
}

// 2. Handle Update Data
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
            $updateStmt = $pdo->prepare("UPDATE expenses SET pocket_id = ?, amount = ?, category_id = ?, description = ?, date = ? WHERE id = ? AND user_id = ?");
            $updateStmt->execute([$pocket_id, $amount, $category_id, $description, $date, $id, $user_id]);
            $message = "Data berhasil diperbarui!";
            
            // Refresh data terbaru agar form terupdate
            $stmt->execute([$id, $user_id]);
            $data = $stmt->fetch();
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = "Gagal memperbarui data.";
        }
    } else {
        $error = "Mohon lengkapi semua data wajib.";
    }
}

$pockets = getUserPockets($pdo, $user_id, true);
$currentPocketId = $data['pocket_id'] ?: getDefaultPocketId($pdo, $user_id);
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
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Edit Transaksi</h1>
            <p class="text-slate-500 text-sm">Perbarui detail pengeluaran.</p>
        </div>
        <a href="transactions.php" class="text-sm text-slate-500 hover:text-slate-800 transition">
            &larr; Kembali
        </a>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-2 text-sm border border-emerald-200">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
        <form action="" method="POST" class="space-y-5">
            <?= csrfField() ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Tanggal Transaksi</label>
                    <input type="date" name="date" value="<?= $data['date'] ?>" required
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Nominal (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-2.5 text-slate-400">Rp</span>
                        <input type="number" name="amount" value="<?= $data['amount'] ?>" required
                            class="w-full pl-12 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800 font-medium">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Pocket</label>
                <div class="relative">
                    <select name="pocket_id" id="pocket_id" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800 appearance-none bg-white">
                        <?php foreach ($pockets as $pocket): ?>
                            <option value="<?= (int)$pocket['id'] ?>" <?= (int)$pocket['id'] === (int)$currentPocketId ? 'selected' : '' ?>>
                                <?= e($pocket['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="absolute right-4 top-3.5 w-4 h-4 text-slate-400 pointer-events-none">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Kategori</label>
                <div class="relative">
                    <select name="category_id" id="category_id" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800 appearance-none bg-white">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" data-pocket-id="<?= e($cat['pocket_id'] ?? '') ?>" <?= (int)$cat['id'] === (int)$data['category_id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?><?= !empty($cat['pocket_id']) ? ' - ' . e($cat['pocket_name']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="absolute right-4 top-3.5 w-4 h-4 text-slate-400 pointer-events-none">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Keterangan / Detail</label>
                <textarea name="description" rows="3"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition text-slate-800"><?= e($data['description']) ?></textarea>
            </div>

            <div class="pt-2 flex gap-3">
                <a href="transactions.php" class="w-1/3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium py-3 rounded-lg transition text-center">Batal</a>
                <button type="submit" class="w-2/3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 rounded-lg transition shadow-md hover:shadow-lg flex justify-center items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                    </svg>
                    Simpan Perubahan
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
