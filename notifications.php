<?php
require_once 'includes/header.php';
checkLogin();

$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';
$previewTitle = '';
$previewText = '';

$dayNames = [
    1 => 'Senin',
    2 => 'Selasa',
    3 => 'Rabu',
    4 => 'Kamis',
    5 => 'Jumat',
    6 => 'Sabtu',
    7 => 'Minggu'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';
    $settingId = filter_input(INPUT_POST, 'setting_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $groupId = sanitizeGroupId($_POST['group_id'] ?? '');
    $pocketIdRaw = $_POST['pocket_id'] ?? '';
    $pocketId = filter_var($pocketIdRaw, FILTER_VALIDATE_INT) ?: null;
    $weeklyEnabled = isset($_POST['weekly_enabled']) ? 1 : 0;
    $weeklyDay = (int)($_POST['weekly_day'] ?? 1);
    $monthlyEnabled = isset($_POST['monthly_enabled']) ? 1 : 0;
    $monthlyDay = (int)($_POST['monthly_day'] ?? 1);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $weeklyDay = max(1, min(7, $weeklyDay));
    $monthlyDay = max(1, min(28, $monthlyDay));

    try {
        if (in_array($action, ['preview_weekly', 'preview_monthly', 'test_weekly', 'test_monthly'], true) && $settingId) {
            $period = strpos($action, 'monthly') !== false ? 'monthly' : 'weekly';
            $stmt = $pdo->prepare("SELECT * FROM notification_settings WHERE id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$settingId, $user_id]);
            $setting = $stmt->fetch();

            if (!$setting) {
                $error = 'Konfigurasi notifikasi tidak ditemukan.';
            } elseif (strpos($action, 'preview_') === 0) {
                $previewTitle = 'Preview ' . ($period === 'weekly' ? 'Mingguan' : 'Bulanan') . ' - ' . $setting['name'];
                $previewText = buildFinancialSummaryMessage($pdo, $setting, $period);
            } else {
                $sendResult = sendSummaryNotification($pdo, $setting, $period, true);
                if ($sendResult['ok']) {
                    $message = 'Test notifikasi berhasil dikirim ke Group ID tujuan.';
                    $previewTitle = 'Isi Test Terkirim';
                    $previewText = $sendResult['message'];
                } else {
                    $error = 'Test notifikasi gagal dikirim. Cek Histori Notifikasi untuk detail.';
                    $previewTitle = 'Isi Test yang Dicoba';
                    $previewText = $sendResult['message'];
                }
            }
        } elseif ($action === 'delete' && $settingId) {
            $stmt = $pdo->prepare("DELETE FROM notification_settings WHERE id = ? AND user_id = ?");
            $stmt->execute([$settingId, $user_id]);
            $message = 'Konfigurasi notifikasi berhasil dihapus.';
        } else {
            if ($name === '') {
                $error = 'Nama notifikasi wajib diisi.';
            } elseif (!$groupId) {
                $error = 'Group ID tujuan wajib diisi.';
            } elseif (!$weeklyEnabled && !$monthlyEnabled) {
                $error = 'Aktifkan minimal notifikasi mingguan atau bulanan.';
            }

            if (!$error && $pocketId) {
                $stmt = $pdo->prepare("SELECT id FROM pockets WHERE id = ? AND user_id = ? LIMIT 1");
                $stmt->execute([$pocketId, $user_id]);
                if (!$stmt->fetchColumn()) {
                    $error = 'Pocket tidak valid.';
                }
            }

            if (!$error && $action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO notification_settings
                        (user_id, pocket_id, name, group_id, weekly_enabled, weekly_day, monthly_enabled, monthly_day, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $pocketId, $name, $groupId, $weeklyEnabled, $weeklyDay, $monthlyEnabled, $monthlyDay, $isActive]);
                $message = 'Konfigurasi notifikasi berhasil ditambahkan.';
            } elseif (!$error && $action === 'update' && $settingId) {
                $stmt = $pdo->prepare("
                    UPDATE notification_settings
                    SET pocket_id = ?,
                        name = ?,
                        group_id = ?,
                        weekly_enabled = ?,
                        weekly_day = ?,
                        monthly_enabled = ?,
                        monthly_day = ?,
                        is_active = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$pocketId, $name, $groupId, $weeklyEnabled, $weeklyDay, $monthlyEnabled, $monthlyDay, $isActive, $settingId, $user_id]);
                $message = 'Konfigurasi notifikasi berhasil diperbarui.';
            }
        }
    } catch (PDOException $e) {
        $error = 'Gagal menyimpan konfigurasi notifikasi.';
    }
}

$pockets = getUserPockets($pdo, $user_id, true);

$stmt = $pdo->prepare("
    SELECT ns.*, p.name AS pocket_name
    FROM notification_settings ns
    LEFT JOIN pockets p ON p.id = ns.pocket_id
    WHERE ns.user_id = ?
    ORDER BY ns.is_active DESC, ns.name ASC
");
$stmt->execute([$user_id]);
$settings = $stmt->fetchAll();

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://'
    . ($_SERVER['HTTP_HOST'] ?? 'domain.com')
    . ($basePath === '' || $basePath === '.' ? '' : $basePath);
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-800">Notifikasi Summary</h1>
    <p class="text-slate-500 text-sm">Atur ringkasan mingguan dan bulanan yang dikirim otomatis ke Group ID WhatsApp tertentu.</p>
    <a href="notification_logs.php" class="inline-flex items-center mt-3 px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm transition">Lihat Histori Notifikasi</a>
</div>

<?php if ($message): ?>
    <div class="mb-4 bg-emerald-50 text-emerald-700 px-4 py-3 rounded-lg text-sm border border-emerald-200"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="mb-4 bg-red-50 text-red-700 px-4 py-3 rounded-lg text-sm border border-red-200"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($previewText): ?>
    <section class="mb-6 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/70 flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-800"><?= e($previewTitle ?: 'Preview Summary') ?></h2>
            <span class="text-xs text-slate-500">Format pesan WhatsApp</span>
        </div>
        <pre class="p-5 text-sm leading-6 text-slate-700 whitespace-pre-wrap overflow-x-auto font-sans"><?= e($previewText) ?></pre>
    </section>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Tambah Notifikasi</h2>
        <form method="POST" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Nama</label>
                <input type="text" name="name" required placeholder="Contoh: Summary Keluarga"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Group ID Tujuan</label>
                <input type="text" name="group_id" required placeholder="12036...@g.us"
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Scope Pocket</label>
                <select name="pocket_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition bg-white">
                    <option value="">Semua Pocket</option>
                    <?php foreach ($pockets as $pocket): ?>
                        <option value="<?= (int)$pocket['id'] ?>"><?= e($pocket['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rounded-lg border border-slate-200 p-4 space-y-4">
                <label class="flex items-center justify-between gap-3 text-sm text-slate-700">
                    <span>Summary mingguan</span>
                    <span class="relative inline-flex">
                        <input type="checkbox" name="weekly_enabled" value="1" class="toggle-input sr-only" checked>
                        <span class="toggle-switch"></span>
                    </span>
                </label>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Hari Kirim</label>
                    <select name="weekly_day" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none bg-white">
                        <?php foreach ($dayNames as $dayNumber => $dayName): ?>
                            <option value="<?= $dayNumber ?>" <?= $dayNumber === 1 ? 'selected' : '' ?>><?= e($dayName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 p-4 space-y-4">
                <label class="flex items-center justify-between gap-3 text-sm text-slate-700">
                    <span>Summary bulanan</span>
                    <span class="relative inline-flex">
                        <input type="checkbox" name="monthly_enabled" value="1" class="toggle-input sr-only" checked>
                        <span class="toggle-switch"></span>
                    </span>
                </label>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Tanggal Kirim</label>
                    <select name="monthly_day" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none bg-white">
                        <?php for ($day = 1; $day <= 28; $day++): ?>
                            <option value="<?= $day ?>"><?= $day ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <label class="flex items-center justify-between gap-3 text-sm text-slate-700">
                <span>Status aktif</span>
                <span class="relative inline-flex">
                    <input type="checkbox" name="is_active" value="1" class="toggle-input sr-only" checked>
                    <span class="toggle-switch"></span>
                </span>
            </label>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg transition">Simpan Notifikasi</button>
        </form>
    </section>

    <section class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
            <h2 class="text-base font-semibold text-slate-800">Daftar Notifikasi</h2>
        </div>

        <?php if (!$settings): ?>
            <div class="p-8 text-center text-sm text-slate-500">Belum ada konfigurasi notifikasi.</div>
        <?php endif; ?>

        <div class="divide-y divide-slate-100">
            <?php foreach ($settings as $setting): ?>
                <form method="POST" class="p-5 space-y-4">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="setting_id" value="<?= (int)$setting['id'] ?>">

                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-800 truncate"><?= e($setting['name']) ?></h3>
                                <span class="px-2 py-1 rounded-full text-xs border <?= $setting['is_active'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200' ?>">
                                    <?= $setting['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                                <span class="px-2 py-1 rounded-full text-xs border bg-indigo-50 text-indigo-700 border-indigo-100">
                                    <?= $setting['pocket_id'] ? e($setting['pocket_name']) : 'Semua Pocket' ?>
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1 truncate"><?= e($setting['group_id']) ?></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" name="action" value="preview_weekly" class="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm transition">Preview Mingguan</button>
                            <button type="submit" name="action" value="preview_monthly" class="px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-sm transition">Preview Bulanan</button>
                            <button type="submit" name="action" value="test_weekly" onclick="return confirm('Kirim test summary mingguan ke Group ID ini?')" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm transition">Test Mingguan</button>
                            <button type="submit" name="action" value="test_monthly" onclick="return confirm('Kirim test summary bulanan ke Group ID ini?')" class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm transition">Test Bulanan</button>
                            <button type="submit" class="px-3 py-2 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-sm transition">Update</button>
                            <button type="submit" name="action" value="delete" onclick="return confirm('Hapus konfigurasi notifikasi ini?')" class="px-3 py-2 bg-white border border-red-200 hover:bg-red-50 text-red-600 rounded-lg text-sm transition">Delete</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Nama</label>
                            <input type="text" name="name" value="<?= e($setting['name']) ?>" required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Group ID Tujuan</label>
                            <input type="text" name="group_id" value="<?= e($setting['group_id']) ?>" required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-slate-500 mb-1.5 tracking-wide">Scope Pocket</label>
                            <select name="pocket_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none bg-white">
                                <option value="">Semua Pocket</option>
                                <?php foreach ($pockets as $pocket): ?>
                                    <option value="<?= (int)$pocket['id'] ?>" <?= (int)$setting['pocket_id'] === (int)$pocket['id'] ? 'selected' : '' ?>><?= e($pocket['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="flex items-center justify-between gap-3 text-sm text-slate-700 rounded-lg border border-slate-200 px-4 py-3">
                            <span>Status aktif</span>
                            <span class="relative inline-flex">
                                <input type="checkbox" name="is_active" value="1" <?= $setting['is_active'] ? 'checked' : '' ?> class="toggle-input sr-only">
                                <span class="toggle-switch"></span>
                            </span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                            <label class="flex items-center justify-between gap-3 text-sm text-slate-700">
                                <span>Summary mingguan</span>
                                <span class="relative inline-flex">
                                    <input type="checkbox" name="weekly_enabled" value="1" <?= $setting['weekly_enabled'] ? 'checked' : '' ?> class="toggle-input sr-only">
                                    <span class="toggle-switch"></span>
                                </span>
                            </label>
                            <select name="weekly_day" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none bg-white">
                                <?php foreach ($dayNames as $dayNumber => $dayName): ?>
                                    <option value="<?= $dayNumber ?>" <?= (int)$setting['weekly_day'] === $dayNumber ? 'selected' : '' ?>><?= e($dayName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 space-y-3">
                            <label class="flex items-center justify-between gap-3 text-sm text-slate-700">
                                <span>Summary bulanan</span>
                                <span class="relative inline-flex">
                                    <input type="checkbox" name="monthly_enabled" value="1" <?= $setting['monthly_enabled'] ? 'checked' : '' ?> class="toggle-input sr-only">
                                    <span class="toggle-switch"></span>
                                </span>
                            </label>
                            <select name="monthly_day" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none bg-white">
                                <?php for ($day = 1; $day <= 28; $day++): ?>
                                    <option value="<?= $day ?>" <?= (int)$setting['monthly_day'] === $day ? 'selected' : '' ?>><?= $day ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="mt-6 bg-slate-900 text-slate-100 rounded-xl p-5">
    <h2 class="text-sm font-semibold mb-2">URL Cron</h2>
    <p class="text-sm text-slate-300 mb-3">Jalankan endpoint berikut dari cron hosting setelah mengisi environment variable <span class="font-mono text-white">SUMMARY_CRON_TOKEN</span>.</p>
    <div class="space-y-2 text-xs font-mono break-all">
        <div class="bg-slate-800 rounded-lg px-3 py-2"><?= e($baseUrl . '/api/send_summary.php?period=weekly&key=ISI_TOKEN') ?></div>
        <div class="bg-slate-800 rounded-lg px-3 py-2"><?= e($baseUrl . '/api/send_summary.php?period=monthly&key=ISI_TOKEN') ?></div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
