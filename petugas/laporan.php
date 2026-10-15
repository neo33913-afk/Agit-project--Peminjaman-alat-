<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['admin', 'petugas']);

sinkron_terlambat($pdo);

$rows = $pdo->query(
    "SELECT p.id_peminjaman, p.tanggal_pinjam, p.tanggal_jatuh_tempo, p.status,
            MAX(u.nama_lengkap) AS peminjam,
            GROUP_CONCAT(a.nama_alat ORDER BY a.nama_alat SEPARATOR ', ') AS barang,
            COALESCE(SUM(d.jumlah_alat), 0) AS total,
            MAX(pg.tanggal_kembali) AS tgl_kembali,
            MAX(pg.kondisi_alat) AS kondisi_kembali,
            MAX(pg.denda) AS denda
     FROM peminjaman p
     JOIN users u ON u.id = p.id_user
     LEFT JOIN detail_peminjaman d ON d.id_peminjaman = p.id_peminjaman
     LEFT JOIN alat a ON a.id_alat = d.id_alat
     LEFT JOIN pengembalian pg ON pg.id_peminjaman = p.id_peminjaman
     GROUP BY p.id_peminjaman
     ORDER BY p.tanggal_pinjam DESC, p.id_peminjaman DESC"
)->fetchAll();

$labelLaporan = [
    'dikembalikan' => 'Selesai',
    'dipinjam'     => 'Dipinjam',
    'terlambat'    => 'Terlambat',
    'diajukan'     => 'Menunggu',
    'ditolak'      => 'Ditolak',
];

$cnt = ['total' => count($rows), 'selesai' => 0, 'dipinjam' => 0, 'menunggu' => 0];
foreach ($rows as $r) {
    if ($r['status'] === 'dikembalikan') $cnt['selesai']++;
    if (in_array($r['status'], ['dipinjam', 'terlambat'], true)) $cnt['dipinjam']++;
    if ($r['status'] === 'diajukan') $cnt['menunggu']++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan | Sistem Peminjaman Alat</title>

    <link rel="stylesheet" href="../asset/css/bootstrap.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f8ff;
            color: #123b70;
            padding: 30px 20px;
        }

        .container-page {
            max-width: 1200px;
            margin: auto;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .header h2 {
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #073b78;
        }

        .header p {
            font-size: 12px;
            color: #8090aa;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-back,
        .btn-print {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 10px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: .25s;
            white-space: nowrap;
        }

        .btn-back {
            background: white;
            color: #176bc1;
            border: 1px solid #dce8fa;
        }

        .btn-back:hover {
            background: #e8f2ff;
            color: #0864cf;
            transform: translateX(-3px);
        }

        .btn-print {
            background: linear-gradient(135deg, #0864cf, #3196ff);
            color: white;
            border: none;
            box-shadow: 0 5px 12px rgba(25, 117, 224, .18);
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(25, 117, 224, .25);
        }

        /* STATISTIK */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 23px;
            border: 1px solid #dce8fa;
            border-radius: 17px;
            box-shadow: 0 6px 20px rgba(27, 82, 150, .04);
            transition: .25s;
            animation: fadeIn .5s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(27, 82, 150, .09);
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 17px;
        }

        .stat-icon {
            width: 43px;
            height: 43px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            background: #e8f2ff;
            color: #1478df;
        }

        .stat-label {
            font-size: 11px;
            color: #8291a8;
            margin-bottom: 7px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 700;
            color: #123b70;
        }

        .stat-description {
            font-size: 10px;
            color: #93a1b5;
            margin-top: 7px;
        }

        /* PANEL */
        .panel {
            background: white;
            border: 1px solid #dce8fa;
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 6px 20px rgba(27, 82, 150, .04);
            margin-bottom: 25px;
            animation: fadeIn .6s ease;
        }

        .panel-heading {
            margin-bottom: 22px;
        }

        .panel-heading h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .panel-heading p {
            font-size: 11px;
            color: #8998ad;
        }

        /* FILTER */
        .filters {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #435b7a;
            margin-bottom: 9px;
        }

        .form-control-custom {
            width: 100%;
            height: 43px;
            padding: 0 12px;
            border: 1px solid #dce6f3;
            background: #fbfdff;
            border-radius: 9px;
            outline: none;
            font-family: inherit;
            font-size: 11px;
            color: #294565;
            transition: .2s;
        }

        .form-control-custom:focus {
            border-color: #2585ed;
            box-shadow: 0 0 0 3px rgba(37, 133, 237, .1);
        }

        .btn-filter {
            height: 43px;
            padding: 0 19px;
            border: none;
            border-radius: 9px;
            background: #e8f2ff;
            color: #0864cf;
            font-family: inherit;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .btn-filter:hover {
            background: #d7e9ff;
        }

        /* TABEL */
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 16px;
            font-weight: 700;
        }

        .search-box {
            width: 240px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        thead {
            background: #f3f7fd;
        }

        th {
            font-size: 10px;
            font-weight: 600;
            color: #657b98;
            text-align: left;
            padding: 15px 13px;
        }

        td {
            font-size: 11px;
            padding: 16px 13px;
            border-bottom: 1px solid #edf2f9;
            color: #526783;
        }

        tbody tr {
            transition: .2s;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        .borrower {
            font-weight: 600;
            color: #24466f;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .status-selesai {
            background: #e6f9ef;
            color: #16864a;
        }

        .status-dipinjam {
            background: #fff3d9;
            color: #b77908;
        }

        .status-menunggu {
            background: #e8f2ff;
            color: #176bc1;
        }

        .status-ditolak {
            background: #ffe9e9;
            color: #d63b3b;
        }

        .status-terlambat {
            background: #ffe4e6;
            color: #be123c;
        }

        .table-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            font-size: 11px;
            color: #8191a9;
        }

        .pagination {
            display: flex;
            gap: 7px;
        }

        .page-btn {
            min-width: 32px;
            height: 32px;
            padding: 0 9px;
            border: 1px solid #dce6f3;
            border-radius: 8px;
            background: white;
            color: #647995;
            cursor: pointer;
            font-family: inherit;
            font-size: 11px;
        }

        .page-btn.active {
            background: #1478df;
            color: white;
            border-color: #1478df;
        }

        .empty-state {
            display: none;
            padding: 30px;
            text-align: center;
            color: #8998ad;
            font-size: 12px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* RESPONSIVE */
        @media (max-width: 950px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 20px 12px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .header h2 {
                font-size: 21px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions button,
            .header-actions a {
                flex: 1;
                padding: 11px 8px;
                font-size: 10px;
            }

            .stats {
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-number {
                font-size: 23px;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 18px 14px;
            }

            .table-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }

            .table-footer {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        /* CETAK LAPORAN */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .no-print,
            .filters,
            .pagination,
            .btn-back,
            .btn-print {
                display: none !important;
            }

            .container-page {
                max-width: 100%;
            }

            .panel,
            .stat-card {
                box-shadow: none;
                break-inside: avoid;
            }

            .stats {
                grid-template-columns: repeat(4, 1fr);
            }

            .table-responsive {
                overflow: visible;
            }

            table {
                white-space: normal;
            }

            th,
            td {
                font-size: 9px;
                padding: 9px 5px;
            }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar_petugas.php'; ?>

<div class="nv-page">
<div class="container-page">

    <!-- HEADER -->
    <div class="header">
        <div>
            <h2>Laporan Peminjaman</h2>
            <p>Dashboard / Laporan / Laporan Peminjaman Alat</p>
        </div>

        <div class="header-actions no-print">
            <a href="dashboard_petugas.php" class="btn-back">
                &larr; Kembali
            </a>

            <button class="btn-print" onclick="window.print()">
                &#128438; Cetak Laporan
            </button>
        </div>
    </div>

    <!-- STATISTIK -->
    <div class="stats">

        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon">&#128203;</div></div>
            <div class="stat-label">Total Peminjaman</div>
            <div class="stat-number" id="total"><?= $cnt['total'] ?></div>
            <div class="stat-description">Seluruh data laporan</div>
        </div>

        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon">&#10003;</div></div>
            <div class="stat-label">Peminjaman Selesai</div>
            <div class="stat-number" id="selesai"><?= $cnt['selesai'] ?></div>
            <div class="stat-description">Barang telah dikembalikan</div>
        </div>

        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon">&#9203;</div></div>
            <div class="stat-label">Sedang Dipinjam</div>
            <div class="stat-number" id="dipinjam"><?= $cnt['dipinjam'] ?></div>
            <div class="stat-description">Peminjaman berlangsung</div>
        </div>

        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon">&#128276;</div></div>
            <div class="stat-label">Menunggu Persetujuan</div>
            <div class="stat-number" id="menunggu"><?= $cnt['menunggu'] ?></div>
            <div class="stat-description">Pengajuan belum diproses</div>
        </div>

    </div>

    <!-- FILTER LAPORAN -->
    <div class="panel">

        <div class="panel-heading">
            <h3>Filter Laporan</h3>
            <p>Pilih periode dan status untuk menyaring data peminjaman.</p>
        </div>

        <div class="filters no-print">

            <div class="form-group">
                <label for="tanggalMulai">Tanggal Mulai</label>
                <input type="date" id="tanggalMulai" class="form-control-custom">
            </div>

            <div class="form-group">
                <label for="tanggalAkhir">Tanggal Akhir</label>
                <input type="date" id="tanggalAkhir" class="form-control-custom">
            </div>

            <div class="form-group">
                <label for="filterStatus">Status Peminjaman</label>
                <select id="filterStatus" class="form-control-custom">
                    <option value="">Semua Status</option>
                    <option value="Selesai">Selesai</option>
                    <option value="Dipinjam">Dipinjam</option>
                    <option value="Terlambat">Terlambat</option>
                    <option value="Menunggu">Menunggu</option>
                    <option value="Ditolak">Ditolak</option>
                </select>
            </div>

            <button class="btn-filter" onclick="filterData()">
                &#128269; Terapkan
            </button>

        </div>

    </div>

    <!-- TABEL LAPORAN -->
    <div class="panel">

        <div class="table-header">
            <div>
                <h3>Data Peminjaman Alat</h3>
                <p style="font-size:11px;color:#8998ad;margin-top:6px">
                    Daftar riwayat transaksi peminjaman.
                </p>
            </div>

            <input type="text" id="search"
                   class="form-control-custom search-box no-print"
                   placeholder="Cari nama atau barang..."
                   oninput="filterData()">
        </div>

        <div class="table-responsive">
            <table id="tabelLaporan">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>ID Pinjam</th>
                        <th>Nama Peminjam</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Tanggal Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Tanggal Kembali</th>
                        <th>Kondisi</th>
                        <th>Denda</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody id="dataLaporan">
                <?php foreach ($rows as $i => $r): ?>
                    <?php
                    $lbl = $labelLaporan[$r['status']] ?? ucfirst($r['status']);
                    ?>
                    <tr data-tanggal="<?= e($r['tanggal_pinjam']) ?>" data-status="<?= e($lbl) ?>">
                        <td><?= $i + 1 ?></td>
                        <td><?= e(kode('PNJ', (int)$r['id_peminjaman'])) ?></td>
                        <td class="borrower"><?= e($r['peminjam']) ?></td>
                        <td><?= e($r['barang'] ?? '-') ?></td>
                        <td><?= (int)$r['total'] ?></td>
                        <td><?= e(date('d-m-Y', strtotime($r['tanggal_pinjam']))) ?></td>
                        <td><?= e(date('d-m-Y', strtotime($r['tanggal_jatuh_tempo']))) ?></td>
                        <td><?= $r['tgl_kembali'] ? e(date('d-m-Y', strtotime($r['tgl_kembali']))) : '-' ?></td>
                        <td><?= e(ucfirst($r['kondisi_kembali'] ?? '-')) ?></td>
                        <td><?= $r['denda'] !== null && (float)$r['denda'] > 0 ? e(rupiah($r['denda'])) : '-' ?></td>
                        <td><span class="status status-<?= e(strtolower($lbl)) ?>"><?= e($lbl) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div id="emptyState" class="empty-state" <?= $rows ? '' : 'style="display:block"' ?>>
                <?= $rows ? 'Tidak ada data yang sesuai dengan filter.' : 'Belum ada data peminjaman.' ?>
            </div>
        </div>

        <div class="table-footer">
            <span id="jumlahData">Menampilkan <?= $cnt['total'] ?> data laporan</span>
        </div>

    </div>

</div>
</div>

<script>
    function filterData() {
        const mulai = document.getElementById('tanggalMulai').value;
        const akhir = document.getElementById('tanggalAkhir').value;
        const status = document.getElementById('filterStatus').value;
        const cari = document.getElementById('search').value.toLowerCase();

        const baris = document.querySelectorAll('#dataLaporan tr');

        let jumlah = 0, selesai = 0, dipinjam = 0, menunggu = 0;

        baris.forEach(function (row) {
            const tanggal = row.dataset.tanggal;
            const statusBaris = row.dataset.status;
            const teks = row.innerText.toLowerCase();

            const tampil =
                (!mulai || tanggal >= mulai) &&
                (!akhir || tanggal <= akhir) &&
                (!status || statusBaris === status) &&
                (!cari || teks.includes(cari));

            row.style.display = tampil ? '' : 'none';

            if (tampil) {
                jumlah++;
                if (statusBaris === 'Selesai') selesai++;
                if (statusBaris === 'Dipinjam' || statusBaris === 'Terlambat') dipinjam++;
                if (statusBaris === 'Menunggu') menunggu++;
            }
        });

        document.getElementById('total').textContent = jumlah;
        document.getElementById('selesai').textContent = selesai;
        document.getElementById('dipinjam').textContent = dipinjam;
        document.getElementById('menunggu').textContent = menunggu;

        document.getElementById('jumlahData').textContent =
            'Menampilkan ' + jumlah + ' data laporan';

        document.getElementById('emptyState').style.display =
            jumlah === 0 ? 'block' : 'none';

        document.getElementById('tabelLaporan').style.display =
            jumlah === 0 ? 'none' : 'table';
    }
</script>

</body>
</html>
