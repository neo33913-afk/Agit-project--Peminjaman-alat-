# Cara pasang

1. Salin isi folder ini ke folder proyekmu (satu level dengan `login.html` dan `logout.php`).
   Folder `asset/css/bootstrap.css`, `asset/css/style.css`, dan `asset/js/bootstrap.bundle.js`
   milikmu tetap dipakai; di sini hanya ditambah `asset/css/navbar.css`.
2. Buka `config/koneksi.php`, ganti `$DB_NAME` dengan nama database kamu.
3. Jalankan `database_patch.sql` di phpMyAdmin (menambah status `ditolak` pada tabel peminjaman).
4. Login harus mengisi `$_SESSION['user_id']` dengan `users.id`, dan memverifikasi password
   dengan `password_verify()` (user baru/edit password disimpan dengan `password_hash()`).
5. Butuh PHP 8.0 atau lebih baru (XAMPP versi terbaru sudah cukup).

Denda per hari diatur di `includes/auth.php` (`DENDA_PER_HARI`).
