<?php
/*
 * persetujuan.php
 * Halaman untuk Petugas menyetujui / menolak pengajuan peminjaman dari user (peminjam).
 * Disesuaikan dengan struktur tabel peminjaman yang ada.
 */
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['petugas', 'admin']);

// Ambil daftar pengajuan yang statusnya 'diajukan' (menunggu ACC)
$stmt = $pdo->prepare("
    SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
           u.nama_lengkap AS nama_peminjam, u.username
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id
    WHERE p.status = 'diajukan'
    ORDER BY p.tanggal_pinjam DESC
");
$stmt->execute();
$pengajuan = $stmt->fetchAll();

// Proses ACC / Tolak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'], $_POST['id'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $aksi = $_POST['aksi']; // 'setujui' atau 'tolak'

    if (in_array($aksi, ['setujui', 'tolak'], true)) {
        $pdo->beginTransaction();
        try {
            if ($aksi === 'tolak') {
                // Kembalikan stok jika ditolak
                $details = $pdo->prepare("SELECT id_alat, jumlah_alat FROM detail_peminjaman WHERE id_peminjaman = ?");
                $details->execute([$id]);
                foreach ($details->fetchAll() as $d) {
                    $pdo->prepare("UPDATE alat SET stok = stok + ? WHERE id_alat = ?")
                        ->execute([(int)$d['jumlah_alat'], (int)$d['id_alat']]);
                }
            }

            $statusBaru = $aksi === 'setujui' ? 'dipinjam' : 'ditolak';
            $upd = $pdo->prepare("UPDATE peminjaman SET status = ? WHERE id_peminjaman = ? AND status = 'diajukan'");
            $upd->execute([$statusBaru, $id]);

            $pdo->commit();
            set_flash('success', $aksi === 'setujui' ? 'Pengajuan berhasil disetujui.' : 'Pengajuan ditolak dan stok dikembalikan.');
            redirect('persetujuan.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('error', 'Gagal memproses pengajuan.');
            redirect('persetujuan.php');
        }
    }
}

$nvFlash = pull_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Peminjaman | Petugas</title>
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
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-menunggu { background: #fff3cd; color: #856404; }
        .btn { padding: 6px 12px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; }
        .btn-setujui { background: #22c55e; color: #fff; }
        .btn-tolak { background: #ef4444; color: #fff; }
        .btn-kembali {
            display: inline-block;
            background: #64748b;
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 16px;
        }
        .btn-kembali:hover { background: #475569; color: #fff; }
        .btn:hover { opacity: .9; }
        .empty { text-align: center; padding: 40px; color: #8090aa; }
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 12px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar_petugas.php'; ?>

<div class="container-page">
    <div class="card">
        <div class="header-row">
            <h2 style="margin: 0; color: #073b78;">Persetujuan Peminjaman</h2>
            <a href="dashboard_petugas.php" class="btn-kembali">← Kembali ke Dashboard</a>
        </div>
        <p style="color: #8090aa; margin-bottom: 20px;">
            Daftar pengajuan peminjaman dari user yang menunggu ACC petugas (status: diajukan).
        </p>

        <?php if ($nvFlash): ?>
            <div class="nv-flash nv-flash-<?= e($nvFlash['type']) ?>" style="margin-bottom: 16px;">
                <?= e($nvFlash['msg']) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pengajuan)): ?>
            <div class="empty">Belum ada pengajuan yang menunggu persetujuan.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Peminjam</th>
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pengajuan as $i => $row): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td>
                                <strong><?= e($row['nama_peminjam']) ?></strong><br>
                                <small>@<?= e($row['username']) ?></small>
                            </td>
                            <td><?= e(date('d/m/Y', strtotime($row['tanggal_pinjam']))) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($row['tanggal_jatuh_tempo']))) ?></td>
                            <td><span class="badge badge-menunggu"><?= e(ucfirst($row['status'])) ?></span></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Setujui pengajuan ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$row['id_peminjaman'] ?>">
                                    <input type="hidden" name="aksi" value="setujui">
                                    <button type="submit" class="btn btn-setujui">Setujui</button>
                                </form>
                                <form method="post" style="display:inline; margin-left:6px;" onsubmit="return confirm('Tolak pengajuan ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$row['id_peminjaman'] ?>">
                                    <input type="hidden" name="aksi" value="tolak">
                                    <button type="submit" class="btn btn-tolak">Tolak</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>