<?php
/*
 * cek_barang.php
 * Halaman untuk Petugas melihat stok dan kondisi barang/alat.
 * Disesuaikan dengan struktur tabel alat.
 */
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['petugas', 'admin']);

// Ambil data alat
$stmt = $pdo->query("
    SELECT id_alat, nama_alat, stok, lokasi, kondisi, id_kategori
    FROM alat
    ORDER BY nama_alat ASC
");
$barang = $stmt->fetchAll();

$nvFlash = pull_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Barang | Petugas</title>
    <link rel="stylesheet" href="../asset/css/bootstrap.css">
    <link rel="stylesheet" href="../asset/css/style.css">
    <link rel="stylesheet" href="../asset/css/navbar.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .container-page { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #e8eff9; font-size: 14px; }
        th { background: #f4f8ff; color: #123b70; font-weight: 600; }
        .stok-habis { color: #ef4444; font-weight: 600; }
        .stok-sedikit { color: #f59e0b; font-weight: 600; }
        .stok-aman { color: #22c55e; font-weight: 600; }
        .kondisi-baik { color: #22c55e; font-weight: 500; }
        .kondisi-rusak { color: #ef4444; font-weight: 500; }
        .empty { text-align: center; padding: 40px; color: #8090aa; }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            background: #fff;
            color: #176bc1;
            border: 1px solid #dce8fa;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            margin-bottom: 16px;
            transition: .2s;
        }
        .btn-back:hover {
            background: #e8f2ff;
            color: #0864cf;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar_petugas.php'; ?>

<div class="container-page">
    <a href="dashboard_petugas.php" class="btn-back">&larr; Kembali ke Dashboard</a>

    <div class="card">
        <h2 style="margin-bottom: 8px; color: #073b78;">Cek Stok Barang</h2>
        <p style="color: #8090aa; margin-bottom: 20px;">
            Daftar alat/barang beserta stok, lokasi, dan kondisinya.
        </p>

        <?php if ($nvFlash): ?>
            <div class="nv-flash nv-flash-<?= e($nvFlash['type']) ?>" style="margin-bottom: 16px;">
                <?= e($nvFlash['msg']) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($barang)): ?>
            <div class="empty">Belum ada data barang.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Alat</th>
                        <th>Stok</th>
                        <th>Lokasi</th>
                        <th>Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($barang as $i => $row): ?>
                        <?php
                        $stok = (int)$row['stok'];
                        $stokClass = $stok <= 0 ? 'stok-habis' : ($stok <= 5 ? 'stok-sedikit' : 'stok-aman');
                        $kondisiClass = (strtolower($row['kondisi'] ?? '') === 'baik') ? 'kondisi-baik' : 'kondisi-rusak';
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= e($row['nama_alat']) ?></strong></td>
                            <td class="<?= $stokClass ?>"><?= $stok ?></td>
                            <td><?= e($row['lokasi'] ?? '-') ?></td>
                            <td class="<?= $kondisiClass ?>"><?= e(ucfirst($row['kondisi'] ?? '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>