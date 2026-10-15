<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['peminjam']);

$alatList = $pdo->query(
    "SELECT id_alat, nama_alat, stok FROM alat
     WHERE stok > 0 AND kondisi <> 'rusak berat'
     ORDER BY nama_alat"
)->fetchAll();

$f = [
    'id_alat'         => (int)($_GET['id'] ?? 0),
    'jumlah'          => '1',
    'tanggal_pinjam'  => date('Y-m-d'),
    'tanggal_kembali' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f = [
        'id_alat'         => (int)($_POST['id_alat'] ?? 0),
        'jumlah'          => trim($_POST['jumlah'] ?? ''),
        'tanggal_pinjam'  => trim($_POST['tanggal_pinjam'] ?? ''),
        'tanggal_kembali' => trim($_POST['tanggal_kembali'] ?? ''),
    ];

    $valid = fn(string $d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    $hariIni = date('Y-m-d');

    if (!ctype_digit($f['jumlah']) || (int)$f['jumlah'] < 1) {
        $errors[] = 'Jumlah alat minimal 1.';
    }
    if (!$valid($f['tanggal_pinjam']) || $f['tanggal_pinjam'] < $hariIni) {
        $errors[] = 'Tanggal peminjaman tidak boleh sebelum hari ini.';
    }
    if (!$valid($f['tanggal_kembali']) || $f['tanggal_kembali'] < $f['tanggal_pinjam']) {
        $errors[] = 'Tanggal pengembalian tidak boleh sebelum tanggal peminjaman.';
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $s = $pdo->prepare('SELECT nama_alat, stok, kondisi FROM alat WHERE id_alat = ? FOR UPDATE');
            $s->execute([$f['id_alat']]);
            $alat = $s->fetch();

            if (!$alat || $alat['kondisi'] === 'rusak berat') {
                $errors[] = 'Alat yang dipilih tidak tersedia.';
            } elseif ((int)$f['jumlah'] > (int)$alat['stok']) {
                $errors[] = 'Stok ' . $alat['nama_alat'] . ' hanya tersisa ' . (int)$alat['stok'] . ' unit.';
            }

            if (!$errors) {
                $pdo->prepare(
                    "INSERT INTO peminjaman (id_user, tanggal_pinjam, tanggal_jatuh_tempo, status)
                     VALUES (?, ?, ?, 'diajukan')"
                )->execute([$me['id'], $f['tanggal_pinjam'], $f['tanggal_kembali']]);

                $idPinjam = (int)$pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO detail_peminjaman (id_peminjaman, id_alat, jumlah_alat) VALUES (?, ?, ?)'
                )->execute([$idPinjam, $f['id_alat'], (int)$f['jumlah']]);

                // Kurangi stok alat
                $pdo->prepare('UPDATE alat SET stok = stok - ? WHERE id_alat = ?')
                    ->execute([(int)$f['jumlah'], $f['id_alat']]);
            }

            if ($errors) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
                set_flash('success', 'Pengajuan peminjaman berhasil dikirim. Tunggu persetujuan petugas.');
                redirect('dashboard.php');
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Gagal menyimpan pengajuan. Coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pinjam Alat | AlatKu</title>

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

        .container-pinjam {
            max-width: 850px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 25px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #064584;
        }

        .breadcrumb-text {
            font-size: 12px;
            color: #7c91b4;
            margin-bottom: 30px;
        }

        .pinjam-card {
            background: #fff;
            border: 1px solid #d7e5ff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 25px rgba(20, 76, 140, 0.04);
        }

        .icon-box {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #0870e8, #3298ff);
            color: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 17px;
            box-shadow: 0 5px 12px rgba(0, 112, 232, 0.15);
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #073b78;
            margin-bottom: 7px;
        }

        .card-description {
            font-size: 12px;
            line-height: 1.8;
            color: #8294b3;
            margin-bottom: 28px;
        }

        .section-title {
            font-size: 13px;
            font-weight: 600;
            color: #123f75;
            padding-bottom: 15px;
            border-bottom: 1px solid #e1eaff;
            margin-bottom: 22px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 600;
            color: #23466f;
            margin-bottom: 10px;
        }

        .required {
            color: #ef4444;
        }

        .form-control,
        .form-select {
            width: 100%;
            min-height: 46px;
            padding: 12px 14px;
            border: 1px solid #d5e3fa;
            border-radius: 11px;
            background-color: #fbfdff;
            color: #34516f;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            transition: .25s;
        }

        .form-control::placeholder {
            color: #9aacc8;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #2388ff;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(35, 136, 255, 0.10);
            outline: none;
        }

        input[type="file"] {
            padding: 9px 12px;
        }

        textarea.form-control {
            min-height: 105px;
            resize: vertical;
        }

        .form-hint {
            color: #91a2bd;
            font-size: 11px;
            margin-top: 8px;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #e7eef9;
        }

        .btn-back,
        .btn-submit,
        .btn-laporan {
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: .3s;
        }

        .btn-back {
            background: #f0f5fc;
            color: #52708f;
            border: 1px solid #e0e9f6;
        }

        .btn-laporan {
            background: #e8f2ff;
            color: #0864cf;
            border: 1px solid #dce8fa;
        }

        .btn-submit {
            color: #fff;
            background: linear-gradient(135deg, #0869d8, #3298ff);
            border: none;
            box-shadow: 0 5px 12px rgba(0, 105, 216, .15);
        }

        .btn-back:hover {
            background: #e2ebf8;
            color: #073b78;
        }

        .btn-laporan:hover {
            background: #d7e9ff;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0, 105, 216, .22);
        }

        @media (max-width: 576px) {
            body {
                padding: 22px 14px;
            }

            .pinjam-card {
                padding: 22px 18px;
            }

            .page-title {
                font-size: 22px;
            }

            .form-footer {
                flex-direction: column-reverse;
            }

            .btn-back,
            .btn-submit,
            .btn-laporan {
                width: 100%;
                text-align: center;
            }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar_user.php'; ?>

<div class="nv-page">
<div class="container-pinjam">

    <!-- JUDUL HALAMAN -->
    <h2 class="page-title">Pinjam Alat</h2>

    <div class="breadcrumb-text">
        Dashboard / Peminjaman / Pinjam Alat
    </div>

    <!-- CARD FORM -->
    <div class="pinjam-card">

        <div class="icon-box">+</div>

        <h4 class="card-title">Formulir Peminjaman Alat</h4>

        <p class="card-description">
            Lengkapi informasi di bawah ini untuk mengajukan
            peminjaman alat yang kamu butuhkan.
        </p>

        <?php if ($errors): ?>
            <div class="nv-alert" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$alatList): ?>
            <div class="nv-alert" role="alert">
                Saat ini belum ada alat yang tersedia untuk dipinjam.
            </div>
        <?php endif; ?>

        <form action="pinjam_alat.php" method="POST">
            <?= csrf_field() ?>

            <div class="section-title">
                Informasi Peminjaman
            </div>

            <div class="row">

                <!-- NAMA -->
                <div class="col-12 mb-4">
                    <label for="nama" class="form-label">Nama Peminjam</label>

                    <input type="text" class="form-control" id="nama"
                           value="<?= e($me['nama_lengkap'] ?: $me['username']) ?>" readonly>
                </div>

                <!-- ALAT -->
                <div class="col-md-6 mb-4">
                    <label for="alat" class="form-label">
                        Pilih Alat <span class="required">*</span>
                    </label>

                    <select class="form-select" id="alat" name="id_alat" required>
                        <option value="">Pilih alat</option>
                        <?php foreach ($alatList as $a): ?>
                            <option value="<?= (int)$a['id_alat'] ?>"
                                    data-stok="<?= (int)$a['stok'] ?>"
                                    <?= (int)$f['id_alat'] === (int)$a['id_alat'] ? 'selected' : '' ?>>
                                <?= e($a['nama_alat']) ?> (stok <?= (int)$a['stok'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- JUMLAH -->
                <div class="col-md-6 mb-4">
                    <label for="jumlah" class="form-label">
                        Jumlah Alat <span class="required">*</span>
                    </label>

                    <input type="number" class="form-control" id="jumlah" name="jumlah"
                           min="1" value="<?= e($f['jumlah']) ?>" required
                           placeholder="Masukkan jumlah alat">
                    <div class="form-hint" id="infoStok"></div>
                </div>

                <!-- TANGGAL PINJAM -->
                <div class="col-md-6 mb-4">
                    <label for="tanggal_pinjam" class="form-label">
                        Tanggal Peminjaman <span class="required">*</span>
                    </label>

                    <input type="date" class="form-control" id="tanggal_pinjam"
                           name="tanggal_pinjam" value="<?= e($f['tanggal_pinjam']) ?>" required>
                </div>

                <!-- TANGGAL KEMBALI -->
                <div class="col-md-6 mb-2">
                    <label for="tanggal_kembali" class="form-label">
                        Tanggal Pengembalian <span class="required">*</span>
                    </label>

                    <input type="date" class="form-control" id="tanggal_kembali"
                           name="tanggal_kembali" value="<?= e($f['tanggal_kembali']) ?>" required>

                    <div class="form-hint">
                        Keterlambatan dikenakan denda <?= e(rupiah(DENDA_PER_HARI)) ?> per hari.
                    </div>
                </div>

            </div>

            <!-- TOMBOL -->
            <div class="form-footer">

                <a href="dashboard.php" class="btn-back">
                    ← Kembali ke Dashboard
                </a>

                

                <button type="submit" class="btn-submit" <?= $alatList ? '' : 'disabled' ?>>
                    Ajukan Peminjaman →
                </button>

            </div>

        </form>

    </div>
</div>
</div>

<script>
    const pinjam = document.getElementById('tanggal_pinjam');
    const kembali = document.getElementById('tanggal_kembali');
    const alat = document.getElementById('alat');
    const jumlah = document.getElementById('jumlah');
    const infoStok = document.getElementById('infoStok');

    const sekarang = new Date();
    const tanggalHariIni =
        sekarang.getFullYear() + '-' +
        String(sekarang.getMonth() + 1).padStart(2, '0') + '-' +
        String(sekarang.getDate()).padStart(2, '0');

    pinjam.min = tanggalHariIni;
    kembali.min = pinjam.value || tanggalHariIni;

    pinjam.addEventListener('change', function () {
        kembali.min = this.value;

        if (kembali.value && kembali.value < this.value) {
            kembali.value = '';
        }
    });

    function aturStok() {
        const opt = alat.options[alat.selectedIndex];
        const stok = opt && opt.dataset.stok ? parseInt(opt.dataset.stok, 10) : 0;

        if (stok > 0) {
            jumlah.max = stok;
            if (parseInt(jumlah.value, 10) > stok) jumlah.value = stok;
            infoStok.textContent = 'Maksimal ' + stok + ' unit.';
        } else {
            jumlah.removeAttribute('max');
            infoStok.textContent = '';
        }
    }

    alat.addEventListener('change', aturStok);
    aturStok();
</script>

</body>
</html>