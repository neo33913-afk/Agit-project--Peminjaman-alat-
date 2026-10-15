-- Jalankan sekali di phpMyAdmin (tab SQL) pada database kamu.
-- Menambahkan status "ditolak" dan "terlambat" pada tabel peminjaman.
-- CATATAN: pada screenshot nilai ke-4 enum terpotong ("terl..."). Kalau nilai aslinya
-- bukan 'terlambat', samakan penulisannya di bawah ini dan di includes/auth.php.

ALTER TABLE peminjaman
  MODIFY status ENUM('diajukan','dipinjam','dikembalikan','terlambat','ditolak')
  NOT NULL DEFAULT 'diajukan';
