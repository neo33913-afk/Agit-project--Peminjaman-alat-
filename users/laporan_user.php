<?php
/*
 * laporan.php khusus untuk User (Peminjam)
 * Hanya menampilkan data peminjaman milik user yang sedang login.
 */
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['peminjam']);

sinkron_terlambat($pdo);

$stmt = $pdo->prepare(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
            GROUP_CONCAT(a.nama_alat ORDER BY a.nama_alat SEPARATOR ', ') AS barang,
            COALESCE(SUM(d.jumlah_alat), 0) AS total,
            MAX(pg.tanggal_kembali) AS tgl_kembali,
            MAX(pg.kondisi_alat) AS kondisi_kembali,
            MAX(pg.denda) AS denda
     FROM peminjaman p
     LEFT JOIN detail_peminjaman d ON d.id_peminjaman = p.id_peminjaman
     LEFT JOIN alat a ON a.id_alat = d.id_alat
     LEFT JOIN pengembalian pg ON pg.id_peminjaman = p.id_peminjaman
     WHERE p.id_user = ?
     GROUP BY p.id_peminjaman
     ORDER BY p.tanggal_pinjam DESC"
);
$stmt->execute([$me['id']]);
$rows = $stmt->fetchAll();

$labelLaporan = [
    'dikembalikan' => 'Selesai',
    'dipinjam'     => 'Dipinjam',
    'terlambat'    => 'Terlambat',
    'diajukan'     => 'Menunggu',
    'ditolak'      => 'Ditolak',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Saya | Sistem Peminjaman Alat</title>
    <link rel="stylesheet" href="../asset/css/bootstrap.css">
    <link rel="stylesheet" href="../asset/css/navbar.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f4f8ff; color: #123b70; padding: 30px 20px; }
        .container-page { max-width: 1100px; margin: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .btn-back { background: #fff; color: #176bc1; border: 1px solid #dce8fa; padding: 10px 16px; border-radius: 10px; text-decoration: none; font-weight: 600; }
        .panel { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e8eff9; font-size: 13px; }
        th { background: #f3f7fd; color: #657b98; }
        .status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar_user.php'; ?>

<div class="container-page">
    <div class="header">
        <div>
            <h2>Laporan Peminjaman Saya</h2>
            <p style="color:#8090aa;font-size:13px;">Hanya menampilkan data milik kamu.</p>
        </div>
        <a href="dashboard.php" class="btn-back">&larr; Kembali</a>
    </div>

    <div class="panel">
        <?php if (empty($rows)): ?>
            <p style="text-align:center;color:#8090aa;padding:40px;">Belum ada data peminjaman.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nama Alat</th>
                        <th>Jumlah</th>
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Tanggal Kembali</th>
                        <th>Kondisi</th>
                        <th>Denda</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php $lbl = $labelLaporan[$r['status']] ?? ucfirst($r['status']); ?>
                        <tr>
                            <td><?= e($r['barang'] ?? '-') ?></td>
                            <td><?= (int)$r['total'] ?></td>
                            <td><?= e(date('d-m-Y', strtotime($r['tanggal_pinjam']))) ?></td>
                            <td><?= e(date('d-m-Y', strtotime($r['tanggal_jatuh_tempo']))) ?></td>
                            <td><?= $r['tgl_kembali'] ? e(date('d-m-Y', strtotime($r['tgl_kembali']))) : '-' ?></td>
                            <td><?= e(ucfirst($r['kondisi_kembali'] ?? '-')) ?></td>
                            <td><?= ($r['denda'] ?? 0) > 0 ? e(rupiah($r['denda'])) : '-' ?></td>
                            <td><span class="status"><?= e($lbl) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
