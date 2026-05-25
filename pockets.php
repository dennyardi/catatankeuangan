<?php
require_once 'includes/header.php';
checkLogin();

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $group_id = sanitizeGroupId($_POST['group_id'] ?? '');
    $budget_amount = normalizeMoneyInput($_POST['budget_amount'] ?? 0);
    $budget_enabled = isset($_POST['budget_enabled']) ? 1 : 0;
    $pocket_id = filter_input(INPUT_POST, 'pocket_id', FILTER_VALIDATE_INT);

    try {
        if ($action === 'create') {
            if ($name === '') {
                $error = 'Nama pocket wajib diisi.';
            } else {
                if ($group_id) {
                    $stmt = $pdo->prepare("SELECT id FROM pockets WHERE group_id = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$group_id]);
                    if ($stmt->fetchColumn()) {
                        $error = 'Group ID sudah dipakai oleh pocket aktif lain.';
                    }
                }

                if (!$error) {
                    $stmt = $pdo->prepare("INSERT INTO pockets (user_id, name, group_id, budget_amount, budget_enabled, is_active) VALUES (?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$user_id, $name, $group_id, $budget_amount, $budget_enabled]);
                    $message = 'Pocket baru berhasil dibuat.';
                }
            }
        } elseif ($action === 'update' && $pocket_id) {
            if ($name === '') {
                $error = 'Nama pocket wajib diisi.';
            } else {
                if ($group_id) {
                    $stmt = $pdo->prepare("SELECT id FROM pockets WHERE group_id = ? AND id <> ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$group_id, $pocket_id]);
                    if ($stmt->fetchColumn()) {
                        $error = 'Group ID sudah dipakai oleh pocket aktif lain.';
                    }
                }

                if (!$error) {
                    $stmt = $pdo->prepare("UPDATE pockets SET name = ?, group_id = ?, budget_amount = ?, budget_enabled = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$name, $group_id, $budget_amount, $budget_enabled, $pocket_id, $user_id]);
                    $message = 'Konfigurasi pocket berhasil diperbarui.';
                }
            }
        } elseif ($action === 'toggle' && $pocket_id) {
            $stmt = $pdo->prepare("SELECT is_active FROM pockets WHERE id = ? AND user_id = ?");
            $stmt->execute([$pocket_id, $user_id]);
            $current = $stmt->fetchColumn();

            if ($current !== false) {
                $newStatus = ((int)$current === 1) ? 0 : 1;
                $stmt = $pdo->prepare("UPDATE pockets SET is_active = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$newStatus, $pocket_id, $user_id]);
                $message = $newStatus ? 'Pocket diaktifkan kembali.' : 'Pocket dinonaktifkan.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Gagal menyimpan konfigurasi pocket.';
    }
}

$pockets = getUserPockets($pdo, $user_id, false);
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Konfigurasi Pocket</h1>
    <p class="text-slate-500 text-sm">Pisahkan sumber pencatatan berdasarkan pocket dan Group ID WhatsApp.</p>
</div>

<?php if ($message): ?>
    <div class="mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg text-sm border border-emerald-200"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-lg text-sm border border-red-200"><?= e($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Tambah Pocket</h2>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Nama Pocket</label>
                <input type="text" name="name" required placeholder="Contoh: Pengeluaran Ayan"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Group ID WhatsApp</label>
                <input type="text" name="group_id" placeholder="12036...@g.us"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                <p class="text-xs text-slate-400 mt-2">Kosongkan jika pocket hanya dipakai input manual.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Budget Per Periode</label>
                <input type="text" name="budget_amount" placeholder="Contoh: 3000000"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
            </div>
            <label class="flex items-center justify-between gap-3 text-sm text-slate-700">
                <span>Aktifkan limit pocket</span>
                <span class="relative inline-flex">
                    <input type="checkbox" name="budget_enabled" value="1" class="toggle-input sr-only">
                    <span class="toggle-switch"></span>
                </span>
            </label>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition">Simpan Pocket</button>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-base font-semibold text-slate-800">Daftar Pocket</h2>
        </div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($pockets as $pocket): ?>
                <form method="POST" class="p-5 space-y-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="pocket_id" value="<?= (int)$pocket['id'] ?>">

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-800"><?= e($pocket['name']) ?></h3>
                            <span class="px-2 py-1 rounded-full text-xs border <?= $pocket['is_active'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200' ?>">
                                <?= $pocket['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                            <span class="px-2 py-1 rounded-full text-xs border <?= !empty($pocket['budget_enabled']) ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : 'bg-slate-50 text-slate-500 border-slate-200' ?>">
                                <?= !empty($pocket['budget_enabled']) ? 'Limit On' : 'Limit Off' ?>
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="px-3 py-2 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-sm transition">Update</button>
                            <button type="submit" name="action" value="toggle" class="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm transition">
                                <?= $pocket['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Nama</label>
                            <input type="text" name="name" value="<?= e($pocket['name']) ?>" required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Group ID</label>
                            <input type="text" name="group_id" value="<?= e($pocket['group_id']) ?>"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <div class="grid grid-cols-[1fr_auto] gap-3 items-end">
                            <div>
                                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Budget</label>
                                <input type="text" name="budget_amount" value="<?= e(number_format((float)$pocket['budget_amount'], 0, ',', '.')) ?>"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                            </div>
                            <label class="flex flex-col items-center gap-1 text-xs text-slate-600 pb-0.5">
                                <span>Limit</span>
                                <span class="relative inline-flex">
                                    <input type="checkbox" name="budget_enabled" value="1" <?= !empty($pocket['budget_enabled']) ? 'checked' : '' ?> class="toggle-input sr-only">
                                    <span class="toggle-switch"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
