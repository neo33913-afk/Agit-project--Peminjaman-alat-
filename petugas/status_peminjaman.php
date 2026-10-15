<?php
require_once __DIR__ . '/../includes/auth.php';
$me = auth_user($pdo);

$staff = in_array($me['role'], ['admin', 'petugas'], true);
$kondisiList = ['baik' => 'Baik', 'rusak ringan' => 'Rusak Ringan', 'rusak berat' => 'Rusak Berat'];

sinkron_terlambat($pdo);

/* ---- Aksi petugas/admin: setujui, tolak, kembalikan ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$staff) {
        set_flash('error', 'Kamu tidak punya akses untuk aksi ini.');
        redirect('status_peminjaman.php');
    }
    csrf_check();

    $aksi = $_POST['aksi'] ?? '';
    $idPinjam = (int)($_POST['id'] ?? 0);

    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare('SELECT id_peminjaman, status, tanggal_jatuh_tempo FROM peminjaman WHERE id_peminjaman = ? FOR UPDATE');
        $s->execute([$idPinjam]);
        $p = $s->fetch();

        $detail = $pdo->prepare(
            'SELECT d.id_alat, d.jumlah_alat, a.nama_alat, a.stok
             FROM detail_peminjaman d JOIN alat a ON a.id_alat = d.id_alat
             WHERE d.id_peminjaman = ? FOR UPDATE'
        );

        if (!$p) {
            throw new RuntimeException('Data peminjaman tidak ditemukan.');
        }

        if ($aksi === 'setujui') {
            if ($p['status'] !== 'diajukan') {
                throw new RuntimeException('Pengajuan ini sudah diproses.');
            }
            // Stok sudah dikurangi saat pengajuan di pinjam_alat.php
            // Jadi di sini hanya ubah status saja
            $pdo->prepare("UPDATE peminjaman SET status = 'dipinjam' WHERE id_peminjaman = ?")->execute([$idPinjam]);
            $pesan = 'Pengajuan disetujui.';

        } elseif ($aksi === 'tolak') {
            if ($p['status'] !== 'diajukan') {
                throw new RuntimeException('Pengajuan ini sudah diproses.');
            }
            $pdo->prepare("UPDATE peminjaman SET status = 'ditolak' WHERE id_peminjaman = ?")->execute([$idPinjam]);
            $pesan = 'Pengajuan ditolak.';

        } elseif ($aksi === 'kembalikan') {
            if (!in_array($p['status'], ['dipinjam', 'terlambat'], true)) {
                throw new RuntimeException('Peminjaman ini tidak sedang dipinjam.');
            }
            $kondisi = $_POST['kondisi_alat'] ?? '';
            if (!isset($kondisiList[$kondisi])) {
                throw new RuntimeException('Kondisi alat tidak valid.');
            }

            // Jika rusak, wajib konfirmasi pembayaran cash
            if (in_array($kondisi, ['rusak ringan', 'rusak berat'], true)) {
                if (empty($_POST['bayar_cash'])) {
                    throw new RuntimeException('Pembayaran cash wajib dikonfirmasi untuk barang rusak.');
                }
            }

            $hariIni = date('Y-m-d');
            $telat = hari_terlambat($p['tanggal_jatuh_tempo'], $hariIni);
            $dendaTelat = $telat * DENDA_PER_HARI;

            // Denda berdasarkan kondisi
            $dendaKondisi = 0;
            if ($kondisi === 'rusak ringan') {
                $dendaKondisi = 100000;
            } elseif ($kondisi === 'rusak berat') {
                $dendaKondisi = 500000;
            }

            $denda = $dendaTelat + $dendaKondisi;

            // Setelah pembayaran, kondisi yang disimpan di riwayat juga jadi baik
            $kondisiSimpan = 'baik';

            $pdo->prepare(
                'INSERT INTO pengembalian (id_peminjaman, tanggal_kembali, kondisi_alat, denda) VALUES (?, ?, ?, ?)'
            )->execute([$idPinjam, $hariIni, $kondisiSimpan, $denda]);

            $detail->execute([$idPinjam]);
            $items = $detail->fetchAll();

            // Kembalikan stok dan paksa kondisi alat menjadi baik
            if ($items) {
                $tambah = $pdo->prepare('UPDATE alat SET stok = stok + ?, kondisi = \'baik\' WHERE id_alat = ?');
                foreach ($items as $it) {
                    $tambah->execute([(int)$it['jumlah_alat'], (int)$it['id_alat']]);
                }
            }

            $pesanTambahan = in_array($kondisi, ['rusak ringan', 'rusak berat'], true)
                ? ' Pembayaran cash diterima. Kondisi alat dikembalikan menjadi baik.'
                : ' Stok sudah dikembalikan.';

            $pdo->prepare("UPDATE peminjaman SET status = 'dikembalikan' WHERE id_peminjaman = ?")->execute([$idPinjam]);

            $pesan = 'Alat dikembalikan. ' . ($denda > 0
                ? 'Total denda ' . rupiah($denda) . '.'
                : 'Tidak ada denda.') . $pesanTambahan;
        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }

        $pdo->commit();
        set_flash('success', $pesan);
    } catch (RuntimeException $ex) {
        $pdo->rollBack();
        set_flash('error', $ex->getMessage());
    } catch (PDOException $ex) {
        $pdo->rollBack();
        set_flash('error', 'Terjadi kesalahan database. Coba lagi.');
    }
    redirect('status_peminjaman.php');
}

/* ---- Data ---- */
$sql = "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
               MAX(u.nama_lengkap) AS peminjam,
               GROUP_CONCAT(CONCAT(a.nama_alat, ' (', d.jumlah_alat, ')') ORDER BY a.nama_alat SEPARATOR ', ') AS alat,
               GROUP_CONCAT(DISTINCT k.nama_kategori SEPARATOR ', ') AS kategori,
               COALESCE(SUM(d.jumlah_alat), 0) AS total,
               MAX(pg.tanggal_kembali) AS tgl_kembali,
               MAX(pg.kondisi_alat) AS kondisi_kembali,
               MAX(pg.denda) AS denda
        FROM peminjaman p
        JOIN users u ON u.id = p.id_user
        LEFT JOIN detail_peminjaman d ON d.id_peminjaman = p.id_peminjaman
        LEFT JOIN alat a ON a.id_alat = d.id_alat
        LEFT JOIN kategori k ON k.id_kategori = a.id_kategori
        LEFT JOIN pengembalian pg ON pg.id_peminjaman = p.id_peminjaman "
     . ($staff ? '' : 'WHERE p.id_user = :uid ')
     . 'GROUP BY p.id_peminjaman ORDER BY p.id_peminjaman DESC';

$st = $pdo->prepare($sql);
$st->execute($staff ? [] : ['uid' => $me['id']]);
$rows = $st->fetchAll();

$cnt = ['total' => count($rows), 'menunggu' => 0, 'dipinjam' => 0, 'ditolak' => 0];
foreach ($rows as $r) {
    if ($r['status'] === 'diajukan') $cnt['menunggu']++;
    if (in_array($r['status'], ['dipinjam', 'terlambat'], true)) $cnt['dipinjam']++;
    if ($r['status'] === 'ditolak') $cnt['ditolak']++;
}

function catatan_status(array $r): string
{
    switch ($r['status']) {
        case 'diajukan':
            return 'Pengajuan sedang menunggu pemeriksaan petugas.';
        case 'dipinjam':
            return 'Alat sedang dipinjam. Kembalikan paling lambat ' . tgl_id($r['tanggal_jatuh_tempo']) . '.';
        case 'terlambat':
            $h = hari_terlambat($r['tanggal_jatuh_tempo']);
            return 'Terlambat ' . $h . ' hari. Denda saat ini ' . rupiah($h * DENDA_PER_HARI) . '.';
        case 'dikembalikan':
            return 'Dikembalikan ' . tgl_id($r['tgl_kembali']) . ' dengan kondisi ' . ($r['kondisi_kembali'] ?? '-')
                . '. Denda: ' . rupiah($r['denda'] ?? 0) . '.';
        case 'ditolak':
            return 'Pengajuan ditolak oleh petugas.';
    }
    return '-';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Status Peminjaman | AlatKu</title>

    <link rel="stylesheet" href="../asset/css/bootstrap.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f3f7ff;
            color: #073b78;
            padding: 35px 20px;
        }

        .container-status {
            max-width: 1150px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 25px;
            font-weight: 700;
            color: #064584;
            margin-bottom: 6px;
        }

        .breadcrumb-text {
            font-size: 12px;
            color: #7c91b4;
            margin-bottom: 30px;
        }

        .btn-kembali {
            display: inline-block;
            text-decoration: none;
            background: #fff;
            color: #0869d8;
            border: 1px solid #d7e5ff;
            border-radius: 10px;
            padding: 11px 17px;
            font-size: 12px;
            font-weight: 600;
            transition: .3s;
        }

        .btn-kembali:hover {
            background: #eaf3ff;
            color: #064584;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #d7e5ff;
            border-radius: 15px;
            padding: 22px;
            height: 100%;
            transition: .3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(20, 76, 140, .07);
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 16px;
        }

        .icon-total {
            background: #e7f1ff;
        }

        .icon-menunggu {
            background: #fff4d9;
        }

        .icon-disetujui {
            background: #dcfce7;
        }

        .icon-ditolak {
            background: #fee2e2;
        }

        .stat-label {
            font-size: 12px;
            color: #8192ad;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 25px;
            font-weight: 700;
            color: #073b78;
        }

        .main-card {
            margin-top: 25px;
            background: #fff;
            border: 1px solid #d7e5ff;
            border-radius: 17px;
            padding: 25px;
            box-shadow: 0 6px 20px rgba(20, 76, 140, .04);
        }

        .main-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 22px;
        }

        .main-card-header h4 {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        .main-card-header p {
            font-size: 12px;
            color: #8192ad;
            margin: 0;
        }

        .filter-area {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-area input,
        .filter-area select {
            min-height: 40px;
            padding: 9px 12px;
            border: 1px solid #d7e5ff;
            background: #fbfdff;
            border-radius: 9px;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            outline: none;
        }

        .filter-area input:focus,
        .filter-area select:focus {
            border-color: #3298ff;
        }

        .table-responsive {
            border: 1px solid #e5edf8;
            border-radius: 12px;
        }

        .table {
            margin-bottom: 0;
            min-width: 850px;
            vertical-align: middle;
        }

        .table thead th {
            background: #f5f8ff;
            color: #496787;
            font-size: 11px;
            font-weight: 600;
            padding: 16px 14px;
            border-bottom: 1px solid #e1eaff;
            white-space: nowrap;
        }

        .table tbody td {
            color: #536b88;
            font-size: 11px;
            padding: 16px 14px;
            border-bottom: 1px solid #edf2fa;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: #fafcff;
        }

        .alat-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 150px;
        }

        .alat-icon {
            width: 39px;
            height: 39px;
            background: #edf5ff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .alat-name {
            color: #123f75;
            font-weight: 600;
            font-size: 11px;
        }

        .alat-category {
            font-size: 10px;
            color: #91a2bd;
            margin-top: 4px;
        }

        .badge-status {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .menunggu {
            color: #a16207;
            background: #fef3c7;
        }

        .disetujui {
            color: #15803d;
            background: #dcfce7;
        }

        .ditolak {
            color: #dc2626;
            background: #fee2e2;
        }

        .dipinjam {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .dikembalikan {
            color: #64748b;
            background: #edf2f7;
        }

        .btn-detail {
            border: 1px solid #cfe1ff;
            color: #0870d9;
            background: #f5f9ff;
            padding: 8px 11px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-detail:hover {
            background: #e4efff;
        }

        .empty-state {
            display: none;
            text-align: center;
            padding: 35px 15px;
            color: #8192ad;
            font-size: 12px;
        }

        .empty-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }

        /* MODAL DETAIL */
        .modal-content {
            border: 1px solid #d7e5ff;
            border-radius: 17px;
            font-family: 'Poppins', sans-serif;
        }

        .modal-header {
            border-bottom: 1px solid #e5edf8;
            padding: 22px;
        }

        .modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #073b78;
        }

        .modal-body {
            padding: 23px;
        }

        .detail-row {
            padding: 12px 0;
            border-bottom: 1px solid #edf2fa;
            display: flex;
            justify-content: space-between;
            gap: 15px;
            font-size: 12px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #8192ad;
        }

        .detail-value {
            color: #164c83;
            font-weight: 600;
            text-align: right;
        }

        .catatan {
            background: #f4f8ff;
            border-radius: 10px;
            padding: 13px;
            margin-top: 15px;
            font-size: 11px;
            color: #7186a4;
            line-height: 1.8;
        }

        @media (max-width: 576px) {
            body {
                padding: 22px 14px;
            }

            .page-title {
                font-size: 22px;
            }

            .main-card {
                padding: 17px;
            }

            .filter-area,
            .filter-area input,
            .filter-area select {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar_petugas.php'; ?>

<style>
    .badge-status.terlambat { background: #ffe4e6; color: #be123c; }
    .aksi-wrap { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .aksi-wrap form { margin: 0; }
    .btn-aksi { border: 0; border-radius: 9px; padding: 8px 12px; font-family: inherit; font-size: 11px;
                font-weight: 600; cursor: pointer; transition: .2s; }
    .btn-setuju { background: #dcfce7; color: #15803d; }
    .btn-setuju:hover { background: #bbf7d0; }
    .btn-tolak { background: #fee2e2; color: #b91c1c; }
    .btn-tolak:hover { background: #fecaca; }
    .btn-kembali-alat { background: #dbeafe; color: #1d4ed8; }
    .btn-kembali-alat:hover { background: #bfdbfe; }
</style>

<div class="nv-page">
<div class="container-status">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title"><?= $staff ? 'Data Peminjaman' : 'Status Peminjaman' ?></h2>
            <div class="breadcrumb-text mb-0">
                Dashboard / Peminjaman / Status Peminjaman
            </div>
        </div>

        <a href="dashboard_petugas.php" class="btn-kembali">
            ← Kembali
        </a>
    </div>

    <!-- RINGKASAN STATUS -->
    <div class="row g-3">

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-total">📋</div>
                <div class="stat-label">Total Peminjaman</div>
                <div class="stat-number"><?= $cnt['total'] ?></div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-menunggu">⏳</div>
                <div class="stat-label">Menunggu</div>
                <div class="stat-number"><?= $cnt['menunggu'] ?></div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-disetujui">✓</div>
                <div class="stat-label">Sedang Dipinjam</div>
                <div class="stat-number"><?= $cnt['dipinjam'] ?></div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-ditolak">✕</div>
                <div class="stat-label">Ditolak</div>
                <div class="stat-number"><?= $cnt['ditolak'] ?></div>
            </div>
        </div>

    </div>

    <!-- TABEL RIWAYAT -->
    <div class="main-card">

        <div class="main-card-header">
            <div>
                <h4>Riwayat Peminjaman</h4>
                <p><?= $staff ? 'Proses pengajuan dan pengembalian alat dari semua peminjam.' : 'Pantau status pengajuan dan pengembalian alatmu.' ?></p>
            </div>

            <div class="filter-area">
                <input type="text" id="cariPeminjaman" placeholder="Cari nama alat<?= $staff ? ' / peminjam' : '' ?>...">

                <select id="filterStatus">
                    <option value="">Semua Status</option>
                    <option value="diajukan">Menunggu</option>
                    <option value="dipinjam">Sedang Dipinjam</option>
                    <option value="terlambat">Terlambat</option>
                    <option value="dikembalikan">Dikembalikan</option>
                    <option value="ditolak">Ditolak</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if ($staff): ?><th>Peminjam</th><?php endif; ?>
                        <th>Nama Alat</th>
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th>Kondisi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody id="tabelPeminjaman">
                <?php foreach ($rows as $r): ?>
                    <?php
                    $idKode = kode('PJM', (int)$r['id_peminjaman']);
                    $dendaSekarang = $r['status'] === 'terlambat'
                        ? hari_terlambat($r['tanggal_jatuh_tempo']) * DENDA_PER_HARI : 0;
                    ?>
                    <tr class="data-peminjaman"
                        data-nama="<?= e(mb_strtolower($r['alat'] . ' ' . $r['peminjam'])) ?>"
                        data-status="<?= e($r['status']) ?>"
                        data-id="<?= e($idKode) ?>"
                        data-alat="<?= e($r['alat'] ?? '-') ?>"
                        data-peminjam="<?= e($r['peminjam']) ?>"
                        data-tanggal-pinjam="<?= e(tgl_id($r['tanggal_pinjam'])) ?>"
                        data-tanggal-kembali="<?= e(tgl_id($r['tanggal_jatuh_tempo'])) ?>"
                        data-jumlah="<?= (int)$r['total'] ?> Unit"
                        data-catatan="<?= e(catatan_status($r)) ?>">

                        <td><?= e($idKode) ?></td>

                        <?php if ($staff): ?><td><?= e($r['peminjam']) ?></td><?php endif; ?>

                        <td>
                            <div class="alat-info">
                                <div class="alat-icon">🧰</div>
                                <div>
                                    <div class="alat-name"><?= e($r['alat'] ?? '-') ?></div>
                                    <div class="alat-category"><?= e($r['kategori'] ?? '-') ?></div>
                                </div>
                            </div>
                        </td>

                        <td><?= e(tgl_id($r['tanggal_pinjam'])) ?></td>
                        <td><?= e(tgl_id($r['tanggal_jatuh_tempo'])) ?></td>
                        <td><?= (int)$r['total'] ?> Unit</td>

                        <td>
                            <?php
                            $badge = $r['status'] === 'diajukan' ? 'menunggu' : $r['status'];
                            ?>
                            <span class="badge-status <?= e($badge) ?>"><?= e(status_label($r['status'])) ?></span>
                        </td>

                        <td>
                            <?= e(ucfirst($r['kondisi_kembali'] ?? '-')) ?>
                        </td>

                        <td>
                            <div class="aksi-wrap">
                                <button class="btn-detail" type="button" onclick="lihatDetail(this)">Detail</button>

                                <?php if ($staff && $r['status'] === 'diajukan'): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$r['id_peminjaman'] ?>">
                                        <input type="hidden" name="aksi" value="setujui">
                                        <button class="btn-aksi btn-setuju" type="submit">Setujui</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Tolak pengajuan <?= e($idKode) ?>?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$r['id_peminjaman'] ?>">
                                        <input type="hidden" name="aksi" value="tolak">
                                        <button class="btn-aksi btn-tolak" type="submit">Tolak</button>
                                    </form>
                                <?php elseif ($staff && in_array($r['status'], ['dipinjam', 'terlambat'], true)): ?>
                                    <button class="btn-aksi btn-kembali-alat" type="button"
                                            data-id="<?= (int)$r['id_peminjaman'] ?>"
                                            data-kode="<?= e($idKode) ?>"
                                            data-denda="<?= e(rupiah($dendaSekarang)) ?>"
                                            data-denda-num="<?= (int)$dendaSekarang ?>"
                                            onclick="bukaKembali(this)">Terima Pengembalian</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="pesanKosong" <?= $rows ? '' : 'style="display:block"' ?>>
            <div class="empty-icon">🔍</div>
            <strong><?= $rows ? 'Tidak ada peminjaman ditemukan' : 'Belum ada data peminjaman' ?></strong>
            <p class="mt-2 mb-0">
                <?= $rows ? 'Coba ubah kata kunci atau filter status.' : ($staff ? 'Pengajuan peminjam akan muncul di sini.' : 'Ajukan peminjaman lewat menu Pinjam Alat.') ?>
            </p>
        </div>

    </div>
</div>
</div>

<!-- MODAL DETAIL -->
<div class="modal fade" id="modalDetail" tabindex="-1" aria-labelledby="judulModal" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="judulModal">Detail Peminjaman</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="detail-row"><span class="detail-label">ID Peminjaman</span><span class="detail-value" id="detailId">-</span></div>
                <div class="detail-row"><span class="detail-label">Peminjam</span><span class="detail-value" id="detailPeminjam">-</span></div>
                <div class="detail-row"><span class="detail-label">Nama Alat</span><span class="detail-value" id="detailAlat">-</span></div>
                <div class="detail-row"><span class="detail-label">Tanggal Pinjam</span><span class="detail-value" id="detailPinjam">-</span></div>
                <div class="detail-row"><span class="detail-label">Jatuh Tempo</span><span class="detail-value" id="detailKembali">-</span></div>
                <div class="detail-row"><span class="detail-label">Jumlah</span><span class="detail-value" id="detailJumlah">-</span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="detailStatus">-</span></div>

                <div class="catatan">
                    <strong>Keterangan</strong>
                    <p id="detailCatatan" class="mt-2 mb-0">-</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($staff): ?>
<!-- MODAL PENGEMBALIAN -->
<div class="modal fade" id="modalKembali" tabindex="-1" aria-labelledby="judulKembali" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="kembalikan">
                <input type="hidden" name="id" id="kembaliId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="judulKembali">Terima Pengembalian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    <p style="font-size:13px;margin-bottom:14px">
                        Peminjaman <strong id="kembaliKode">-</strong>.
                        Denda keterlambatan saat ini: <strong id="kembaliDenda" style="color:#dc2626;">Rp 0</strong>.
                    </p>

                    <label for="kondisiAlat" style="font-size:12px;font-weight:600;margin-bottom:6px;display:block">
                        Kondisi alat saat dikembalikan
                    </label>
                    <select name="kondisi_alat" id="kondisiAlat" class="form-select" required>
                        <?php foreach ($kondisiList as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div id="infoDenda" style="margin-top:14px; font-size:13px;">
                        <div id="rowDendaKondisi" style="display:none; margin-bottom:8px;">
                            Denda kondisi alat: <strong id="nilaiDendaKondisi" style="color:#dc2626;">Rp 0</strong>
                        </div>
                        <div>
                            <strong>Total denda: <span id="totalDenda" style="color:#dc2626;">Rp 0</span></strong>
                        </div>
                    </div>

                    <div id="infoBayar" style="margin-top:14px; display:none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="bayar_cash" id="bayarCash" value="1">
                            <label class="form-check-label" for="bayarCash" style="font-size:13px;">
                                Pembayaran cash sudah diterima
                            </label>
                        </div>
                        <small class="text-muted" style="font-size:11px;">
                            Wajib dicentang jika kondisi rusak ringan atau rusak berat.
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-aksi btn-tolak" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-aksi btn-setuju">Simpan Pengembalian</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="../asset/js/bootstrap.bundle.min.js"></script>
<script>
    const cariPeminjaman = document.getElementById('cariPeminjaman');
    const filterStatus = document.getElementById('filterStatus');
    const barisPeminjaman = document.querySelectorAll('.data-peminjaman');
    const pesanKosong = document.getElementById('pesanKosong');

    function filterPeminjaman() {
        const kataKunci = cariPeminjaman.value.toLowerCase().trim();
        const statusDipilih = filterStatus.value;
        let jumlahTampil = 0;

        barisPeminjaman.forEach(function (baris) {
            const cocokNama = baris.dataset.nama.includes(kataKunci);
            const cocokStatus = statusDipilih === '' || baris.dataset.status === statusDipilih;

            if (cocokNama && cocokStatus) {
                baris.style.display = '';
                jumlahTampil++;
            } else {
                baris.style.display = 'none';
            }
        });

        if (barisPeminjaman.length > 0) {
            pesanKosong.style.display = jumlahTampil === 0 ? 'block' : 'none';
        }
    }

    cariPeminjaman.addEventListener('input', filterPeminjaman);
    filterStatus.addEventListener('change', filterPeminjaman);

    function lihatDetail(tombol) {
        const baris = tombol.closest('tr');

        document.getElementById('detailId').textContent = baris.dataset.id;
        document.getElementById('detailPeminjam').textContent = baris.dataset.peminjam;
        document.getElementById('detailAlat').textContent = baris.dataset.alat;
        document.getElementById('detailPinjam').textContent = baris.dataset.tanggalPinjam;
        document.getElementById('detailKembali').textContent = baris.dataset.tanggalKembali;
        document.getElementById('detailJumlah').textContent = baris.dataset.jumlah;
        document.getElementById('detailStatus').textContent =
            baris.querySelector('.badge-status').textContent;
        document.getElementById('detailCatatan').textContent = baris.dataset.catatan;

        new bootstrap.Modal(document.getElementById('modalDetail')).show();
    }

    function bukaKembali(tombol) {
        document.getElementById('kembaliId').value = tombol.dataset.id;
        document.getElementById('kembaliKode').textContent = tombol.dataset.kode;
        document.getElementById('kembaliDenda').textContent = tombol.dataset.denda;

        const dendaTelat = parseInt(tombol.dataset.dendaNum) || 0;

        const selectKondisi = document.getElementById('kondisiAlat');
        const infoBayar = document.getElementById('infoBayar');
        const checkbox = document.getElementById('bayarCash');
        const rowDendaKondisi = document.getElementById('rowDendaKondisi');
        const nilaiDendaKondisi = document.getElementById('nilaiDendaKondisi');
        const totalDenda = document.getElementById('totalDenda');

        function formatRupiah(angka) {
            return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function toggleBayar() {
            const val = selectKondisi.value;
            let dendaKondisi = 0;

            if (val === 'rusak ringan') {
                dendaKondisi = 100000;
            } else if (val === 'rusak berat') {
                dendaKondisi = 500000;
            }

            const total = dendaTelat + dendaKondisi;

            if (dendaKondisi > 0) {
                rowDendaKondisi.style.display = 'block';
                nilaiDendaKondisi.textContent = formatRupiah(dendaKondisi);
                infoBayar.style.display = 'block';
                checkbox.required = true;
            } else {
                rowDendaKondisi.style.display = 'none';
                infoBayar.style.display = 'none';
                checkbox.required = false;
                checkbox.checked = false;
            }

            totalDenda.textContent = formatRupiah(total);
        }

        selectKondisi.onchange = toggleBayar;
        toggleBayar();

        new bootstrap.Modal(document.getElementById('modalKembali')).show();
    }
</script>

</body>
</html>