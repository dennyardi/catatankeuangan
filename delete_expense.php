<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
    $user_id = $_SESSION['user_id'];

    if ($id) {
        try {
            // Pastikan hanya menghapus data milik user yang sedang login (Security Check)
            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
        } catch (PDOException $e) {
            // Silent fail or log error
        }
    }
}

// Kembali ke halaman dashboard
redirect('transactions.php');
?>
