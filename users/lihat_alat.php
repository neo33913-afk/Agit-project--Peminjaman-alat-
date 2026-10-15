<?php
require_once __DIR__ . '/../includes/auth.php';
$me = auth_user($pdo);

$daftar = $pdo->query(
    'SELECT a.id_alat, a.nama_alat, a.stok, a.lokasi, a.kondisi, k.nama_kategori
     FROM alat a LEFT JOIN kategori k ON k.id_kategori = a.id_kategori
     ORDER BY a.nama_alat'
)->fetchAll();

$kategori = $pdo->query('SELECT nama_kategori FROM kategori ORDER BY nama_kategori')->fetchAll(PDO::FETCH_COLUMN);

function ikon_kategori(?string $nama): string
{
    $n = mb_strtolower((string)$nama);
    return match (true) {
        str_contains($n, 'elektronik') => '💻',
        str_contains($n, 'perkakas'), str_contains($n, 'pertukangan') => '🔧',
        str_contains($n, 'lab') => '🔬',
        str_contains($n, 'multimedia') => '📽️',
        str_contains($n, 'olahraga') => '⚽',
        default => '🧰',
    };
}

$isUser = ($me['role'] === 'peminjam');
$laporanUrl = $isUser ? 'laporan_user.php' : 'laporan.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lihat Alat | AlatKu</title>

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

        .container-alat {
            max-width: 1100px;
            margin: auto;
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

        .alat-card {
            background: white;
            border: 1px solid #d7e5ff;
            border-radius: 17px;
            padding: 23px;
            height: 100%;
            box-shadow: 0 6px 20px rgba(20, 76, 140, .04);
            transition: .3s;
        }

        .alat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(20, 76, 140, .10);
        }

        .alat-image {
            width: 100%;
            height: 180px;
            border-radius: 12px;
            background: #edf5ff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .alat-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .placeholder-icon {
            font-size: 60px;
            color: #2c8cf0;
        }

        .kategori {
            display: inline-block;
            font-size: 10px;
            font-weight: 600;
            padding: 6px 10px;
            background: #edf5ff;
            color: #0870d9;
            border-radius: 20px;
            margin-bottom: 12px;
        }

        .alat-card h5 {
            font-size: 16px;
            font-weight: 700;
            color: #073b78;
            margin-bottom: 10px;
        }

        .deskripsi {
            color: #8192ad;
            font-size: 12px;
            line-height: 1.8;
            min-height: 42px;
            margin-bottom: 15px;
        }

        .info-stok {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid #edf1f8;
            padding-top: 15px;
            margin-bottom: 17px;
            gap: 8px;
        }

        .info-stok span {
            font-size: 11px;
            color: #8192ad;
        }

        .info-stok strong {
            font-size: 12px;
            color: #164c83;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .tersedia {
            color: #15803d;
            background: #dcfce7;
        }

        .habis {
            color: #dc2626;
            background: #fee2e2;
        }

        .btn-pinjam {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
            background: linear-gradient(135deg, #0869d8, #3298ff);
            color: white;
            padding: 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            transition: .3s;
        }

        .btn-pinjam:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 105, 216, .2);
        }

        .btn-pinjam.disabled {
            background: #dce5f1;
            color: #8292a8;
            pointer-events: none;
            box-shadow: none;
        }

        .toolbar {
            background: white;
            border: 1px solid #d7e5ff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .toolbar h4 {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }

        .toolbar p {
            font-size: 11px;
            color: #8192ad;
            margin: 6px 0 0;
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-box input,
        .search-box select {
            border: 1px solid #d7e5ff;
            background: #fbfdff;
            border-radius: 10px;
            padding: 11px 13px;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            outline: none;
        }

        .search-box input:focus,
        .search-box select:focus {
            border-color: #3298ff;
        }

        .empty-state {
            display: none;
            background: white;
            border: 1px solid #d7e5ff;
            border-radius: 15px;
            padding: 35px;
            text-align: center;
            color: #8192ad;
            font-size: 13px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-kembali, .btn-laporan {
            display: inline-block;
            text-decoration: none;
            background: white;
            color: #0869d8;
            border: 1px solid #d7e5ff;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-laporan {
            background: linear-gradient(135deg, #0869d8, #3298ff);
            color: white;
            border: none;
        }

        .btn-kembali:hover, .btn-laporan:hover {
            background: #eaf3ff;
            color: #064584;
        }

        .btn-laporan:hover {
            background: #065bb8;
            color: white;
        }

        @media (max-width: 576px) {
            body {
                padding: 22px 14px;
            }

            .page-title {
                font-size: 22px;
            }

            .toolbar {
                align-items: stretch;
            }

            .search-box,
            .search-box input,
            .search-box select {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php
if ($isUser) {
    include __DIR__ . '/../includes/navbar_user.php';
} else {
    include __DIR__ . '/../includes/navbar.php';
}
?>

<div class="nv-page">
<div class="container-alat">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title">Lihat Alat</h2>
            <div class="breadcrumb-text mb-0">
                Dashboard / Data Alat / Lihat Alat
            </div>
        </div>

        <div class="header-actions">
            <a href="dashboard.php" class="btn-kembali">
                ← Kembali
            </a>
            <a href="<?= e($laporanUrl) ?>" class="btn-laporan">
                Lihat Laporan →
            </a>
        </div>
    </div>

    <!-- PENCARIAN DAN FILTER -->
    <div class="toolbar">
        <div>
            <h4>Daftar Alat</h4>
            <p>Temukan alat yang kamu butuhkan untuk dipinjam.</p>
        </div>

        <div class="search-box">
            <input type="text" id="cariAlat" placeholder="Cari nama alat...">

            <select id="filterKategori">
                <option value="">Semua Kategori</option>
                <?php foreach ($kategori as $k): ?>
                    <option value="<?= e(mb_strtolower($k)) ?>"><?= e($k) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- DAFTAR KARTU ALAT -->
    <div class="row g-4" id="daftarAlat">

        <?php foreach ($daftar as $a): ?>
            <?php
            $bisaPinjam = (int)$a['stok'] > 0 && $a['kondisi'] !== 'rusak berat';
            $statusTeks = $a['kondisi'] === 'rusak berat' ? 'Rusak' : ((int)$a['stok'] > 0 ? 'Tersedia' : 'Stok Habis');
            ?>
            <div class="col-md-6 col-lg-4 item-alat"
                 data-nama="<?= e(mb_strtolower($a['nama_alat'])) ?>"
                 data-kategori="<?= e(mb_strtolower((string)$a['nama_kategori'])) ?>">
                <div class="alat-card">

                    <div class="alat-image">
                        <span class="placeholder-icon"><?= ikon_kategori($a['nama_kategori']) ?></span>
                    </div>

                    <span class="kategori"><?= e($a['nama_kategori'] ?? 'Tanpa kategori') ?></span>

                    <h5><?= e($a['nama_alat']) ?></h5>

                    <p class="deskripsi">
                        Lokasi: <?= e($a['lokasi']) ?><br>
                        Kondisi: <?= e(ucfirst($a['kondisi'])) ?>
                    </p>

                    <div class="info-stok">
                        <div>
                            <span>Stok tersedia</span><br>
                            <strong><?= (int)$a['stok'] ?> Unit</strong>
                        </div>
                        <span class="status <?= $bisaPinjam ? 'tersedia' : 'habis' ?>"><?= e($statusTeks) ?></span>
                    </div>

                    <?php if ($me['role'] === 'peminjam'): ?>
                        <?php if ($bisaPinjam): ?>
                            <a href="pinjam_alat.php?id=<?= (int)$a['id_alat'] ?>" class="btn-pinjam">Pinjam Alat →</a>
                        <?php else: ?>
                            <a href="#" class="btn-pinjam disabled" onclick="return false">Tidak Dapat Dipinjam</a>
                        <?php endif; ?>
                    <?php elseif ($me['role'] === 'admin'): ?>
                        <a href="edit_alat.php?id=<?= (int)$a['id_alat'] ?>" class="btn-pinjam">Edit Alat →</a>
                    <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- PESAN JIKA ALAT TIDAK DITEMUKAN -->
    <div class="empty-state mt-4" id="pesanKosong" <?= $daftar ? '' : 'style="display:block"' ?>>
        <div style="font-size:35px; margin-bottom:10px;">🔍</div>
        <strong><?= $daftar ? 'Alat tidak ditemukan' : 'Belum ada data alat' ?></strong>
        <p class="mt-2 mb-0">
            <?= $daftar ? 'Coba gunakan nama atau kategori alat yang berbeda.' : 'Data alat akan muncul setelah admin menambahkannya.' ?>
        </p>
    </div>

</div>
</div>

<script>
    const cariAlat = document.getElementById('cariAlat');
    const filterKategori = document.getElementById('filterKategori');
    const itemAlat = document.querySelectorAll('.item-alat');
    const pesanKosong = document.getElementById('pesanKosong');

    function filterAlat() {
        const kataKunci = cariAlat.value.toLowerCase().trim();
        const kategoriDipilih = filterKategori.value;
        let jumlahTampil = 0;

        itemAlat.forEach(function (item) {
            const cocokNama = item.dataset.nama.includes(kataKunci);
            const cocokKategori = kategoriDipilih === '' || item.dataset.kategori === kategoriDipilih;

            if (cocokNama && cocokKategori) {
                item.style.display = '';
                jumlahTampil++;
            } else {
                item.style.display = 'none';
            }
        });

        pesanKosong.style.display = jumlahTampil === 0 ? 'block' : 'none';
    }

    cariAlat.addEventListener('input', filterAlat);
    filterKategori.addEventListener('change', filterAlat);
</script>

</body>
</html>