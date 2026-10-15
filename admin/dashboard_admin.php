<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['admin']);

sinkron_terlambat($pdo);

$totalAlat       = (int)$pdo->query('SELECT COUNT(*) FROM alat')->fetchColumn();
$totalUser       = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalPeminjaman = (int)$pdo->query('SELECT COUNT(*) FROM peminjaman')->fetchColumn();

$aktivitas = $pdo->query(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.status,
            MAX(u.nama_lengkap) AS peminjam,
            GROUP_CONCAT(a.nama_alat ORDER BY a.nama_alat SEPARATOR ', ') AS alat
     FROM peminjaman p
     JOIN users u ON u.id = p.id_user
     LEFT JOIN detail_peminjaman d ON d.id_peminjaman = p.id_peminjaman
     LEFT JOIN alat a ON a.id_alat = d.id_alat
     GROUP BY p.id_peminjaman
     ORDER BY p.id_peminjaman DESC
     LIMIT 8"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Admin | Sistem Peminjaman Alat</title>

    <link rel="stylesheet" href="../asset/css/bootstrap.css">
    <link rel="stylesheet" href="../asset/css/style.css">
    <link rel="stylesheet" href="../asset/css/navbar.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <!-- KONTEN DASHBOARD -->
    <main class="dashboard-container">

        <!-- SAMBUTAN -->
        <section class="welcome-card">

            <div class="welcome-text">
                <span class="welcome-label">ADMIN DASHBOARD</span>

                <h1>Selamat Datang, <?= e($me['nama_lengkap'] ?: 'Admin') ?>!</h1>

                <p>
                    Kelola data pengguna, petugas, inventaris alat,
                    dan laporan peminjaman melalui satu halaman.
                    Pastikan seluruh data sistem tetap terorganisasi.
                </p>

                <a href="#menu" class="btn-primary">
                    Kelola Sistem
                </a>
            </div>

            <div class="welcome-illustration">
                <div class="illustration-circle">
                    <span class="tool-icon">⚙</span>
                    <span class="tool-icon-small">✓</span>
                </div>
            </div>

        </section>

        <!-- STATISTIK -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon icon-blue">▤</div>
                <div>
                    <p>Total Alat</p>
                    <h3><?= $totalAlat ?></h3>
                    <span>Data inventaris alat</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-light">♙</div>
                <div>
                    <p>Total Pengguna</p>
                    <h3><?= $totalUser ?></h3>
                    <span>Pengguna terdaftar</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-dark">▥</div>
                <div>
                    <p>Total Peminjaman</p>
                    <h3><?= $totalPeminjaman ?></h3>
                    <span>Data pengajuan peminjaman</span>
                </div>
            </div>

        </section>

        <!-- MENU ADMIN -->
        <section class="menu-section" id="menu">

            <div class="section-heading">
                <div>
                    <h2>Menu Administrasi</h2>
                    <p>Pilih menu untuk mengelola sistem peminjaman alat.</p>
                </div>
            </div>

            <div class="menu-grid">

                <!-- LAPORAN -->
                <a href="laporan.php" class="menu-card">
                    <div class="menu-icon menu-icon-light">
                        <span>▥</span>
                    </div>

                    <h3>Laporan</h3>

                    <p>
                        Pantau rekap peminjaman, pengembalian,
                        inventaris, serta aktivitas pengguna.
                    </p>

                    <span class="menu-link">
                        Buka Laporan <span>→</span>
                    </span>
                </a>

                <!-- EDIT USER -->
                <a href="daftar_user.php" class="menu-card">
                    <div class="menu-icon menu-icon-dark">
                        <span>♙</span>
                    </div>

                    <h3>Edit User</h3>

                    <p>
                        Periksa dan perbarui informasi pengguna
                        yang terdaftar dalam sistem.
                    </p>

                    <span class="menu-link">
                        Kelola User <span>→</span>
                    </span>
                </a>

                <!-- EDIT PETUGAS -->
                <a href="daftar_user.php?role=petugas" class="menu-card">
                    <div class="menu-icon menu-icon-light">
                        <span>♧</span>
                    </div>

                    <h3>Edit Petugas</h3>

                    <p>
                        Kelola data petugas, perbarui informasi,
                        dan pastikan data petugas tetap sesuai.
                    </p>

                    <span class="menu-link">
                        Kelola Petugas <span>→</span>
                    </span>
                </a>

                <!-- TAMBAH ALAT -->
                <a href="tambah_alat.php" class="menu-card">
                    <div class="menu-icon menu-icon-blue">
                        <span>＋</span>
                    </div>

                    <h3>Tambah Alat</h3>

                    <p>
                        Masukkan alat baru ke inventaris
                        agar dapat dicatat dan dikelola.
                    </p>

                    <span class="menu-link">
                        Tambah Alat <span>→</span>
                    </span>
                </a>

                <!-- EDIT ALAT -->
                <a href="edit_alat.php" class="menu-card">
                    <div class="menu-icon menu-icon-light">
                        <span>✎</span>
                    </div>

                    <h3>Edit Alat</h3>

                    <p>
                        Perbarui nama, kategori, stok, kondisi,
                        dan informasi alat yang tersimpan.
                    </p>

                    <span class="menu-link">
                        Edit Data Alat <span>→</span>
                    </span>
                </a>

                <!-- DELETE ALAT -->
                <a href="delete_alat.php" class="menu-card">
                    <div class="menu-icon menu-icon-dark">
                        <span>×</span>
                    </div>

                    <h3>Delete Alat</h3>

                    <p>
                        Pilih data alat yang sudah tidak digunakan
                        untuk dihapus dari inventaris.
                    </p>

                    <span class="menu-link">
                        Kelola Penghapusan <span>→</span>
                    </span>
                </a>

            </div>

        </section>

        <!-- AKTIVITAS TERBARU -->
        <section class="activity-section">

            <div class="section-heading">
                <div>
                    <h2>Aktivitas Terbaru</h2>
                    <p>Ringkasan aktivitas sistem peminjaman alat.</p>
                </div>

                <a href="laporan.php" class="see-all">
                    Lihat Laporan →
                </a>
            </div>

            <div class="table-wrapper">

                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Aktivitas</th>
                            <th>Pengguna</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody id="aktivitasBody">
                        <?php if (!$aktivitas): ?>
                        <tr>
                            <td colspan="5" class="empty-data">
                                Belum ada aktivitas yang tercatat.
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($aktivitas as $i => $a): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>Peminjaman <?= e($a['alat'] ?? '-') ?></td>
                            <td><?= e($a['peminjam']) ?></td>
                            <td><?= e(tgl_id($a['tanggal_pinjam'])) ?></td>
                            <td><span class="status-badge"><?= e(status_label($a['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            </div>

        </section>

        <!-- FOOTER -->
        <footer class="dashboard-footer">
            <p>
                &copy; 2026 Sistem Peminjaman Alat.
                Semua hak dilindungi.
            </p>
        </footer>

    </main>


</body>
</html>
