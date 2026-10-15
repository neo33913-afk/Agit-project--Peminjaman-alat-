<?php
session_start();

require_once __DIR__ . '/config/koneksi.php';

// Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi input
if ($username === '' || $password === '') {
    header('Location: login.html?error=' . urlencode('Username dan password wajib diisi.'));
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, username, nama_lengkap, role, password
         FROM users
         WHERE username = :username
         LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {

        session_regenerate_id(true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['user_name'] = $user['nama_lengkap'];
        $_SESSION['role']      = $user['role'];

        // Arahkan sesuai role (sesuaikan path dengan folder kamu)
        $tujuan = match ($user['role']) {
            'admin'   => 'admin/dashboard_admin.php',
            'petugas' => 'petugas/dashboard_petugas.php',
            default   => 'users/dashboard.php',
        };
    
        header('Location: ' . $tujuan);
        exit;
    }

    header('Location: login.html?error=' . urlencode('Username atau password salah.'));
    exit;

} catch (PDOException $e) {
    header('Location: login.html?error=' . urlencode('Terjadi kesalahan saat login.'));
    exit;
}