<?php
// profile.php
require_once 'includes/header.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$message = '';

// --- PROSES UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $new_date = (int)$_POST['start_date_calculation'];
    
    // Validasi sederhana (Hanya terima tanggal 1 atau 25, atau angka 1-28)
    if ($new_date >= 1 && $new_date <= 28) {
        $stmt = $pdo->prepare("UPDATE users SET start_date_calculation = ? WHERE id = ?");
        if ($stmt->execute([$new_date, $user_id])) {
            $message = "<div class='bg-green-100 text-green-700 p-3 rounded mb-4'>✅ Pengaturan berhasil disimpan!</div>";
        }
    }
}

// --- AMBIL DATA USER ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$current_setting = $user['start_date_calculation'] ?? 1;
?>

<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Pengaturan Profil</h1>
    
    <?= $message ?>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
        <form method="POST">
            <?= csrfField() ?>
            <h3 class="font-bold text-slate-700 mb-4 border-b pb-2">Periode Laporan Keuangan</h3>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-600 mb-2">Mulai Perhitungan Tanggal:</label>
                <select name="start_date_calculation" class="w-full p-2 border border-slate-300 rounded focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="1" <?= $current_setting == 1 ? 'selected' : '' ?>>Tanggal 1 (Kalender Standar)</option>
                    <option value="25" <?= $current_setting == 25 ? 'selected' : '' ?>>Tanggal 25 (Periode Gajian)</option>
                    </select>
                <p class="text-xs text-slate-400 mt-2">
                    *Jika memilih <b>Tanggal 25</b>, maka laporan bulan Februari akan menghitung data dari <b>25 Januari s/d 24 Februari</b>.
                </p>
            </div>

            <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">
                Simpan Perubahan
            </button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
