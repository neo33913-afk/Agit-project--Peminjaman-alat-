<?php
session_start();

// Proteksi halaman: wajib login sebagai peminjam
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'peminjam') {
    header('Location: ../login.html');
    exit;
}

require_once __DIR__ . '/../includes/auth.php'; // untuk $pdo jika dibutuhkan

$namaUser = $_SESSION['user_name'] ?? 'Pengguna';
$inisial  = strtoupper(mb_substr($namaUser, 0, 1));
$userId   = (int)$_SESSION['user_id'];

// Ambil data nyata dari database
$totalAlat = $pdo->query("SELECT COUNT(*) FROM alat WHERE stok > 0")->fetchColumn();

$stmtPinjam = $pdo->prepare("
    SELECT COUNT(*) FROM peminjaman WHERE id_user = ?
");
$stmtPinjam->execute([$userId]);
$totalPinjaman = $stmtPinjam->fetchColumn();

$stmtMenunggu = $pdo->prepare("
    SELECT COUNT(*) FROM peminjaman WHERE id_user = ? AND status = 'diajukan'
");
$stmtMenunggu->execute([$userId]);
$totalMenunggu = $stmtMenunggu->fetchColumn();

// Ambil aktivitas peminjaman terbaru (maks 5)
$stmtAktivitas = $pdo->prepare("
    SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
           GROUP_CONCAT(a.nama_alat SEPARATOR ', ') AS nama_alat
    FROM peminjaman p
    LEFT JOIN detail_peminjaman d ON d.id_peminjaman = p.id_peminjaman
    LEFT JOIN alat a ON a.id_alat = d.id_alat
    WHERE p.id_user = ?
    GROUP BY p.id_peminjaman
    ORDER BY p.tanggal_pinjam DESC
    LIMIT 5
");
$stmtAktivitas->execute([$userId]);
$aktivitas = $stmtAktivitas->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard User | Sistem Peminjaman Alat</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="../asset/css/bootstrap.css">

    <!-- CSS Dashboard -->
    <link rel="stylesheet" href="../asset/css/style.css">
    <link rel="stylesheet" href="../asset/css/navbar.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>

<body>

   <?php include __DIR__ . '/../includes/navbar_user.php'; ?>

    <!-- Konten Utama -->
    <main class="dashboard-container">

        <!-- Sambutan -->
        <section class="welcome-card">
            <div class="welcome-text">
                <span class="welcome-label">USER DASHBOARD</span>
                <h1>Selamat Datang, <?= htmlspecialchars($namaUser) ?>!</h1>
                <p>
                    Kelola peminjaman alat dengan mudah.
                    Temukan alat yang kamu butuhkan dan pantau
                    status peminjamanmu di sini.
                </p>

                <a href="#menu" class="btn-primary">
                    Mulai Sekarang
                </a>
            </div>

            <div class="welcome-illustration">
                <div class="illustration-circle">
                    <div class="tool-icon">⚙</div>
                    <div class="tool-icon tool-icon-small">🔧</div>
                </div>
            </div>
        </section>

        <!-- Statistik -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon icon-blue">▤</div>
                <div>
                    <p>Total Alat</p>
                    <h3><?= (int)$totalAlat ?></h3>
                    <span>Alat tersedia di sistem</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-light">↗</div>
                <div>
                    <p>Peminjaman Saya</p>
                    <h3><?= (int)$totalPinjaman ?></h3>
                    <span>Total pengajuan kamu</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-dark">◷</div>
                <div>
                    <p>Menunggu Persetujuan</p>
                    <h3><?= (int)$totalMenunggu ?></h3>
                    <span>Pengajuan belum diproses</span>
                </div>
            </div>

        </section>

        <!-- Menu Utama -->
        <section class="menu-section" id="menu">

            <div class="section-heading">
                <div>
                    <h2>Menu Utama</h2>
                    <p>Pilih layanan yang ingin kamu gunakan.</p>
                </div>
            </div>

            <div class="menu-grid">

                <!-- Pinjam Alat -->
                <a href="pinjam_alat.php" class="menu-card">
                    <div class="menu-icon menu-icon-blue">
                        <span>＋</span>
                    </div>

                    <h3>Pinjam Alat</h3>

                    <p>
                        Ajukan peminjaman alat sesuai kebutuhanmu
                        dengan mengisi formulir peminjaman.
                    </p>

                    <span class="menu-link">
                        Ajukan Peminjaman <span>→</span>
                    </span>
                </a>

                <!-- Lihat Alat -->
                <a href="lihat_alat.php" class="menu-card">
                    <div class="menu-icon menu-icon-light">
                        <span>▤</span>
                    </div>

                    <h3>Lihat Alat</h3>

                    <p>
                        Lihat daftar alat, informasi alat,
                        dan jumlah stok yang tersedia.
                    </p>

                    <span class="menu-link">
                        Lihat Daftar Alat <span>→</span>
                    </span>
                </a>

                <!-- Status Peminjaman -->
                <a href="status_peminjaman.php" class="menu-card">
                    <div class="menu-icon menu-icon-dark">
                        <span>◷</span>
                    </div>

                    <h3>Status Peminjaman</h3>

                    <p>
                        Pantau pengajuan peminjaman dan lihat
                        status persetujuan serta pengembalian.
                    </p>

                    <span class="menu-link">
                        Lihat Status <span>→</span>
                    </span>
                </a>

            </div>
        </section>

        <!-- Aktivitas Terbaru -->
        <section class="activity-section">

            <div class="section-heading">
                <div>
                    <h2>Aktivitas Peminjaman</h2>
                    <p>Ringkasan peminjaman alat terbaru.</p>
                </div>

                <a href="status_peminjaman.php" class="see-all">
                    Lihat Semua →
                </a>
            </div>

            <div class="table-wrapper">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Alat</th>
                            <th>Tanggal Pinjam</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($aktivitas)): ?>
                            <tr>
                                <td colspan="5" class="empty-data">
                                    Belum ada aktivitas peminjaman.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($aktivitas as $i => $row): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($row['nama_alat'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal_pinjam']))) ?></td>
                                    <td><?= htmlspecialchars(date('d-m-Y', strtotime($row['tanggal_jatuh_tempo']))) ?></td>
                                    <td>
                                        <span class="status-badge">
                                            <?= htmlspecialchars(ucfirst($row['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>

        <!-- Footer -->
        <footer class="dashboard-footer">
            <p>
                &copy; 2026 Sistem Peminjaman Alat.
                Semua hak dilindungi.
            </p>
        </footer>

    </main>

</body>
</html>