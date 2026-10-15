<?php
require_once __DIR__ . '/config/koneksi.php';

$username = 'danara';          // ganti dengan username admin kamu
$password = 'Danara123#';       // ganti dengan password yang kamu ketik saat login

$stmt = $pdo->prepare('SELECT username, role, password FROM users WHERE username = :u');
$stmt->execute(['u' => $username]);
$user = $stmt->fetch();

if (!$user) {
    die('Username TIDAK ditemukan di tabel users.');
}

echo 'Username ditemukan, role: ' . htmlspecialchars($user['role']) . '<br>';
echo 'Panjang password di DB: ' . strlen($user['password']) . '<br>';
echo 'Awal password di DB: ' . htmlspecialchars(substr($user['password'], 0, 4)) . '<br>';
echo 'password_verify: ' . (password_verify($password, $user['password']) ? 'COCOK' : 'TIDAK COCOK');