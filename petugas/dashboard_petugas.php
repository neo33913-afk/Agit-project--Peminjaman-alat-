<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['petugas', 'admin']);

// Ambil data peminjaman yang sedang berlangsung
$pengajuan = $pdo->query("
    SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
           u.nama_lengkap AS peminjam,
           GROUP_CONCAT(CONCAT(a.nama_alat, ' (', d.jumlah_alat, ')') ORDER BY a.nama_alat SEPARATOR ', ') AS alat
    FROM peminjaman p
    JOIN users u ON u.id = p.id_user
    LEFT JOIN detail_peminjaman d ON d.id_peminjaman = p.id_peminjaman
    LEFT JOIN alat a ON a.id_alat = d.id_alat
    WHERE p.status IN ('dipinjam', 'terlambat')
    GROUP BY p.id_peminjaman
    ORDER BY p.id_peminjaman DESC
    LIMIT 10
")->fetchAll();

// Statistik
$totalPengajuan = (int)$pdo->query("SELECT COUNT(*) FROM peminjaman")->fetchColumn();
$totalMenunggu  = (int)$pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status = 'diajukan'")->fetchColumn();
$totalDisetujui = (int)$pdo->query("SELECT COUNT(*) FROM peminjaman WHERE status IN ('dipinjam','terlambat')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Petugas | Sistem Peminjaman Alat</title>
    <link rel="stylesheet" href="../asset/css/bootstrap.css">
    <link rel="stylesheet" href="../asset/css/style.css">
    <link rel="stylesheet" href="../asset/css/navbar.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .status-badge { display:inline-block; padding:5px 10px; border-radius:20px; font-size:11px; font-weight:600; }
        .status-dipinjam { background:#dbeafe; color:#1d4ed8; }
        .status-terlambat { background:#ffe4e6; color:#be123c; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar_petugas.php'; ?>

<main class="dashboard-container">

    <!-- SAMBUTAN -->
    <section class="welcome-card">
        <div class="welcome-text">
            <span class="welcome-label">PETUGAS DASHBOARD</span>
            <h1>Selamat Bertugas!</h1>
            <p>
                Kelola peminjaman alat, periksa kondisi barang,
                dan pantau laporan aktivitas melalui satu dashboard.
                Pastikan setiap proses peminjaman berjalan dengan baik.
            </p>
            <a href="#menu" class="btn-primary">Kelola Sekarang</a>
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
                <p>Total Pengajuan</p>
                <h3><?= $totalPengajuan ?></h3>
                <span>Seluruh permohonan peminjaman</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-light">◷</div>
            <div>
                <p>Menunggu Persetujuan</p>
                <h3><?= $totalMenunggu ?></h3>
                <span>Pengajuan perlu diperiksa</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon icon-dark">✓</div>
            <div>
                <p>Sedang Dipinjam</p>
                <h3><?= $totalDisetujui ?></h3>
                <span>Peminjaman yang belum dikembalikan</span>
            </div>
        </div>
    </section>

    <!-- MENU UTAMA -->
    <section class="menu-section" id="menu">
        <div class="section-heading">
            <div>
                <h2>Menu Pengelolaan</h2>
                <p>Pilih pekerjaan yang ingin kamu lakukan.</p>
            </div>
        </div>
        <div class="menu-grid">
            <a href="status_peminjaman.php" class="menu-card">
                <div class="menu-icon menu-icon-blue"><span>✓</span></div>
                <h3>Menyetujui Peminjaman</h3>
                <p>Periksa data peminjam, detail alat, dan tanggal peminjaman. Setujui atau tolak pengajuan sesuai hasil pemeriksaan.</p>
                <span class="menu-link">Periksa Pengajuan <span>→</span></span>
            </a>
            <a href="cek_barang.php" class="menu-card">
                <div class="menu-icon menu-icon-light"><span>▤</span></div>
                <h3>Cek Barang</h3>
                <p>Periksa jumlah stok, kondisi alat, dan ketersediaan barang sebelum dipinjam atau setelah dikembalikan.</p>
                <span class="menu-link">Periksa Barang <span>→</span></span>
            </a>
            <a href="laporan.php" class="menu-card">
                <div class="menu-icon menu-icon-dark"><span>▥</span></div>
                <h3>Laporan</h3>
                <p>Lihat rekap peminjaman, pengembalian, penggunaan alat, dan aktivitas peminjaman untuk membantu pengelolaan.</p>
                <span class="menu-link">Buka Laporan <span>→</span></span>
            </a>
        </div>
    </section>

    <!-- PENGAJUAN TERBARU -->
    <section class="activity-section">
        <div class="section-heading">
            <div>
                <h2>Peminjaman Sedang Berlangsung</h2>
                <p>Alat yang sedang dipinjam dan perlu dikembalikan.</p>
            </div>
            <a href="status_peminjaman.php" class="see-all">Lihat Semua →</a>
        </div>
        <div class="table-wrapper">
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Peminjam</th>
                        <th>Nama Alat</th>
                        <th>Tanggal Pinjam</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$pengajuan): ?>
                    <tr>
                        <td colspan="5" class="empty-data" style="text-align:center;">
                            Belum ada peminjaman yang sedang berlangsung.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pengajuan as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($r['peminjam']) ?></td>
                            <td><?= e($r['alat'] ?? '-') ?></td>
                            <td><?= e(tgl_id($r['tanggal_pinjam'])) ?></td>
                            <td>
                                <span class="status-badge status-<?= e($r['status']) ?>">
                                    <?= $r['status'] === 'terlambat' ? 'Terlambat' : 'Sedang Dipinjam' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="dashboard-footer">
        <p>&copy; 2026 Sistem Peminjaman Alat. Semua hak dilindungi.</p>
    </footer>
</main>

</body>
</html>