<?php
session_start();

require_once __DIR__ . '/config/koneksi.php';

$error = '';
$username = $nama_lengkap = $alamat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username     = trim($_POST['username'] ?? '');
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $alamat       = trim($_POST['alamat'] ?? '');
    $password     = $_POST['password'] ?? '';
    $konfirmasi   = $_POST['konfirmasi_password'] ?? '';

    if ($username === '' || $nama_lengkap === '' || $alamat === '' || $password === '' || $konfirmasi === '') {
        $error = 'Semua field wajib diisi!';
    } elseif (mb_strlen($username) > 50) {
        $error = 'Username maksimal 50 karakter!';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $username)) {
        $error = 'Username hanya boleh berisi huruf, angka, underscore, dan titik!';
    } elseif (mb_strlen($nama_lengkap) > 100) {
        $error = 'Nama lengkap maksimal 100 karakter!';
    } elseif (mb_strlen($alamat) > 200) {
        $error = 'Alamat maksimal 200 karakter!';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter!';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sesuai!';
    } else {
        try {
            // Cek username sudah dipakai atau belum
            $cek = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
            $cek->execute(['username' => $username]);

            if ($cek->fetch()) {
                $error = 'Username sudah digunakan, silakan pilih yang lain!';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, nama_lengkap, alamat, password, role)
                     VALUES (:username, :nama_lengkap, :alamat, :password, :role)'
                );
                $stmt->execute([
                    'username'     => $username,
                    'nama_lengkap' => $nama_lengkap,
                    'alamat'       => $alamat,
                    'password'     => password_hash($password, PASSWORD_DEFAULT),
                    'role'         => 'peminjam',
                ]);

                // Auto-login setelah registrasi
                session_regenerate_id(true);

                $_SESSION['user_id']   = $pdo->lastInsertId();
                $_SESSION['username']  = $username;
                $_SESSION['user_name'] = $nama_lengkap;
                $_SESSION['role']      = 'peminjam';

                // Arahkan ke dashboard (sesuaikan path dengan folder kamu)
                header('Location: users/dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan pada server, coba lagi nanti.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | Sistem Peminjaman Alat</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="./asset/css/bootstrap.css">

    <!-- CSS Register -->
    <link rel="stylesheet" href="./asset/css/style.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

    <div class="container-fluid register-container">
        <div class="row register-row">

            <!-- Panel Kiri -->
            <div class="col-md-4 panel-kiri">
                <div class="welcome-content">
                    <h1>Hallo,</h1>
                    <h2>Selamat Datang</h2>

                    <p>
                        Buat akun baru untuk menggunakan
                        Sistem Peminjaman Alat Kerja/Lab.
                    </p>

                    <p>
                        Daftarkan akun kamu untuk mengajukan
                        peminjaman alat dan memantau status
                        peminjaman dengan lebih mudah.
                    </p>
                </div>
            </div>

            <!-- Panel Kanan -->
            <div class="col-md-4 panel-kanan">

                <h1 class="judul-register" style="margin-top: 20px;">
                    BUAT AKUN
                </h1>

                <p class="deskripsi-register"
                   style="margin-top: 20px; margin-bottom: 30px;">
                    Silakan lengkapi data berikut
                    untuk membuat akun baru.
                </p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST">

                    <div class="form-grid">

                        <!-- Username -->
                        <div class="input-group-register">
                            <label for="username">Username</label>
                            <input
                                type="text"
                                id="username"
                                name="username"
                                placeholder="Masukkan username"
                                maxlength="50"
                                value="<?= htmlspecialchars($username) ?>"
                                required
                            >
                        </div>

                        <!-- Nama Lengkap -->
                        <div class="input-group-register">
                            <label for="nama_lengkap">Nama Lengkap</label>
                            <input
                                type="text"
                                id="nama_lengkap"
                                name="nama_lengkap"
                                placeholder="Masukkan nama lengkap"
                                maxlength="100"
                                value="<?= htmlspecialchars($nama_lengkap) ?>"
                                required
                            >
                        </div>

                        <!-- Password -->
                        <div class="input-group-register">
                            <label for="password">Password</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Masukkan password"
                                minlength="8"
                                required
                            >
                        </div>

                        <!-- Konfirmasi Password -->
                        <div class="input-group-register">
                            <label for="konfirmasi_password">
                                Konfirmasi Password
                            </label>
                            <input
                                type="password"
                                id="konfirmasi_password"
                                name="konfirmasi_password"
                                placeholder="Ulangi password"
                                minlength="8"
                                required
                            >
                        </div>

                        <!-- Alamat -->
                        <div class="input-group-register alamat-panjang">
                            <label for="alamat">Alamat</label>
                            <input
                                type="text"
                                id="alamat"
                                name="alamat"
                                placeholder="Masukkan alamat lengkap"
                                maxlength="200"
                                value="<?= htmlspecialchars($alamat) ?>"
                                required
                            >
                        </div>
                    </div>

                    <button type="submit"
                            class="btn-register"
                            style="margin-top: 40px;">
                        Daftar
                    </button>

                    <p class="login-link" style="margin-top: 20px;">
                        Sudah punya akun?
                        <a href="login.html">Login</a>
                    </p>

                </form>

            </div>

        </div>
    </div>

    <!-- Validasi Password (sisi browser) -->
    <script>
        const form = document.querySelector("form");

        form.addEventListener("submit", function (event) {
            const password =
                document.getElementById("password").value;

            const konfirmasiPassword =
                document.getElementById("konfirmasi_password").value;

            if (password !== konfirmasiPassword) {
                event.preventDefault();
                alert("Konfirmasi password tidak sesuai!");
            }
        });
    </script>

</body>
</html>