<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['admin']);

$kategori = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')->fetchAll();
$kondisiList = ['baik' => 'Baik', 'rusak ringan' => 'Rusak Ringan', 'rusak berat' => 'Rusak Berat'];

$f = ['nama_alat' => '', 'id_kategori' => '', 'kategori_baru' => '', 'lokasi' => '', 'stok' => '', 'kondisi' => 'baik'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ($f as $k => $_) {
        $f[$k] = trim($_POST[$k] ?? '');
    }

    if ($f['nama_alat'] === '' || mb_strlen($f['nama_alat']) > 100) {
        $errors[] = 'Nama alat wajib diisi (maksimal 100 karakter).';
    }
    if ($f['lokasi'] === '' || mb_strlen($f['lokasi']) > 100) {
        $errors[] = 'Lokasi penyimpanan wajib diisi (maksimal 100 karakter).';
    }
    if (!ctype_digit($f['stok']) || (int)$f['stok'] < 1) {
        $errors[] = 'Jumlah alat harus bilangan bulat minimal 1.';
    }
    if (!isset($kondisiList[$f['kondisi']])) {
        $errors[] = 'Kondisi alat tidak valid.';
    }

    $idKategori = 0;
    if ($f['kategori_baru'] !== '') {
        if (mb_strlen($f['kategori_baru']) > 100) {
            $errors[] = 'Nama kategori baru maksimal 100 karakter.';
        }
    } else {
        $ids = array_column($kategori, 'id_kategori');
        if (!in_array((int)$f['id_kategori'], array_map('intval', $ids), true)) {
            $errors[] = 'Pilih kategori alat atau isi kategori baru.';
        } else {
            $idKategori = (int)$f['id_kategori'];
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($f['kategori_baru'] !== '') {
                $cek = $pdo->prepare('SELECT id_kategori FROM kategori WHERE LOWER(nama_kategori) = LOWER(?)');
                $cek->execute([$f['kategori_baru']]);
                $ada = $cek->fetchColumn();
                if ($ada) {
                    $idKategori = (int)$ada;
                } else {
                    $pdo->prepare('INSERT INTO kategori (nama_kategori) VALUES (?)')->execute([$f['kategori_baru']]);
                    $idKategori = (int)$pdo->lastInsertId();
                }
            }

            $pdo->prepare(
                'INSERT INTO alat (id_kategori, nama_alat, stok, lokasi, kondisi) VALUES (?, ?, ?, ?, ?)'
            )->execute([$idKategori, $f['nama_alat'], (int)$f['stok'], $f['lokasi'], $f['kondisi']]);

            $pdo->commit();
            set_flash('success', 'Alat "' . $f['nama_alat'] . '" berhasil ditambahkan.');
            redirect('lihat_alat.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors[] = 'Gagal menyimpan data alat. Coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Tambah Alat | Sistem Peminjaman Alat</title>

    <link rel="stylesheet" href="../asset/css/bootstrap.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fc;
            color: #1e293b;
            min-height: 100vh;
        }

        .navbar-custom {
            background: #ffffff;
            padding: 17px 5%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 3px 15px rgba(15, 45, 90, 0.06);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #123b70;
            font-size: 20px;
            font-weight: 700;
            text-decoration: none;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0056b3, #398bff);
            border-radius: 12px;
            color: white;
            font-size: 21px;
        }

        .admin-label {
            background: #eaf2ff;
            color: #0756ae;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        .container-main {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .breadcrumb-custom {
            display: flex;
            gap: 9px;
            align-items: center;
            margin-bottom: 22px;
            font-size: 13px;
            color: #94a3b8;
        }

        .breadcrumb-custom a {
            color: #1768c4;
            text-decoration: none;
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .page-heading h1 {
            font-size: 27px;
            font-weight: 700;
            color: #123b70;
            margin-bottom: 7px;
        }

        .page-heading p {
            color: #8492a6;
            font-size: 13px;
            margin: 0;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 18px;
            border: 1px solid #dce6f2;
            border-radius: 10px;
            background: #fff;
            color: #35516f;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: #edf5ff;
            color: #0756ae;
            transform: translateY(-2px);
        }

        .form-card {
            background: #fff;
            border: 1px solid #e8eef6;
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(24, 59, 102, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header-custom {
            padding: 23px 28px;
            border-bottom: 1px solid #edf1f7;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .header-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 13px;
            background: #eaf3ff;
            color: #1768c4;
            font-size: 22px;
        }

        .card-header-custom h5 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #163d6b;
        }

        .card-header-custom p {
            margin: 0;
            font-size: 12px;
            color: #94a3b8;
        }

        .form-body {
            padding: 28px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #1768c4;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #dce7f4;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            margin-bottom: 9px;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
        }

        .required {
            color: #ef4444;
        }

        .form-control,
        .form-select {
            width: 100%;
            min-height: 46px;
            border: 1px solid #dce5f0;
            border-radius: 10px;
            padding: 11px 14px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #334155;
            background-color: #fff;
            transition: 0.25s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #398bff;
            box-shadow: 0 0 0 3px rgba(57, 139, 255, 0.12);
            outline: none;
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
        }

        .form-text {
            color: #94a3b8;
            font-size: 11px;
            margin-top: 7px;
        }

        .upload-area {
            border: 2px dashed #cbdcf0;
            border-radius: 13px;
            padding: 25px 15px;
            text-align: center;
            background: #f9fbff;
            transition: 0.3s;
            cursor: pointer;
        }

        .upload-area:hover {
            border-color: #398bff;
            background: #f0f6ff;
        }

        .upload-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .upload-area h6 {
            font-size: 13px;
            font-weight: 600;
            color: #345477;
            margin-bottom: 6px;
        }

        .upload-area p {
            font-size: 11px;
            color: #94a3b8;
            margin: 0;
        }

        #foto {
            display: none;
        }

        .preview-image {
            display: none;
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            margin: 15px auto 0;
            border: 1px solid #dce5f0;
        }

        .form-footer {
            padding: 20px 28px;
            background: #fbfcfe;
            border-top: 1px solid #edf1f7;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-custom {
            border: none;
            border-radius: 10px;
            padding: 12px 22px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-cancel {
            background: #eef2f7;
            color: #64748b;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            color: #334155;
        }

        .btn-submit {
            color: #fff;
            background: linear-gradient(135deg, #0756ae, #398bff);
            box-shadow: 0 5px 14px rgba(23, 104, 196, 0.2);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(23, 104, 196, 0.3);
        }

        .info-box {
            background: #edf6ff;
            border: 1px solid #d6eaff;
            border-radius: 12px;
            padding: 15px 18px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .info-box span {
            font-size: 19px;
        }

        .info-box p {
            color: #476582;
            font-size: 12px;
            line-height: 1.7;
            margin: 0;
        }

        .footer-text {
            text-align: center;
            padding: 5px 15px 25px;
            font-size: 11px;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            .navbar-custom {
                padding: 14px 20px;
            }

            .brand {
                font-size: 16px;
            }

            .container-main {
                margin-top: 25px;
                padding: 0 14px;
            }

            .page-heading h1 {
                font-size: 23px;
            }

            .card-header-custom,
            .form-body {
                padding: 20px;
            }

            .form-footer {
                padding: 18px 20px;
            }

            .btn-custom {
                flex: 1;
                text-align: center;
            }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-main">

        <div class="breadcrumb-custom">
            <a href="dashboard_admin.php">Dashboard</a>
            <span>/</span>
            <a href="lihat_alat.php">Data Alat</a>
            <span>/</span>
            <span>Tambah Alat</span>
        </div>

        <div class="page-heading">
            <div>
                <h1>Tambah Alat Baru</h1>
                <p>Tambahkan data alat yang tersedia untuk dipinjam.</p>
            </div>

            <a href="dashboard_admin.php" class="btn-back">
                ← Kembali
            </a>
        </div>

        <div class="info-box">
            <span>💡</span>
            <p>
                Pastikan data alat diisi dengan benar. Informasi ini akan digunakan
                untuk mengelola inventaris dan membantu pengguna menemukan alat
                yang ingin dipinjam.
            </p>
        </div>

        <?php if ($errors): ?>
            <div class="nv-alert" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="formTambahAlat" method="post" action="tambah_alat.php">
            <?= csrf_field() ?>

            <div class="form-card">

                <div class="card-header-custom">
                    <div class="header-icon">🧰</div>
                    <div>
                        <h5>Informasi Alat</h5>
                        <p>Masukkan identitas dan detail alat.</p>
                    </div>
                </div>

                <div class="form-body">

                    <h6 class="section-title">Informasi Utama</h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nama_alat" class="form-label">
                                    Nama Alat <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="nama_alat" name="nama_alat"
                                       value="<?= e($f['nama_alat']) ?>"
                                       placeholder="Masukkan nama alat" maxlength="100" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="lokasi" class="form-label">
                                    Lokasi Penyimpanan <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="lokasi" name="lokasi"
                                       value="<?= e($f['lokasi']) ?>"
                                       placeholder="Contoh: Gudang A, Rak 02" maxlength="100" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_kategori" class="form-label">Kategori Alat</label>
                                <select class="form-select" id="id_kategori" name="id_kategori">
                                    <option value="">Pilih kategori alat</option>
                                    <?php foreach ($kategori as $k): ?>
                                        <option value="<?= (int)$k['id_kategori'] ?>"
                                            <?= (string)$f['id_kategori'] === (string)$k['id_kategori'] ? 'selected' : '' ?>>
                                            <?= e($k['nama_kategori']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="kategori_baru" class="form-label">Atau Buat Kategori Baru</label>
                                <input type="text" class="form-control" id="kategori_baru" name="kategori_baru"
                                       value="<?= e($f['kategori_baru']) ?>"
                                       placeholder="Contoh: Multimedia" maxlength="100">
                                <div class="form-text">Jika diisi, kategori ini dipakai menggantikan pilihan di samping.</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="section-title mt-2">Stok dan Kondisi</h6>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="stok" class="form-label">
                                    Jumlah Alat <span class="required">*</span>
                                </label>
                                <input type="number" class="form-control" id="stok" name="stok"
                                       value="<?= e($f['stok']) ?>"
                                       placeholder="Masukkan jumlah" min="1" required>
                                <div class="form-text">Jumlah total alat yang dimiliki.</div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="kondisi" class="form-label">
                                    Kondisi Alat <span class="required">*</span>
                                </label>
                                <select class="form-select" id="kondisi" name="kondisi" required>
                                    <?php foreach ($kondisiList as $val => $label): ?>
                                        <option value="<?= e($val) ?>" <?= $f['kondisi'] === $val ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-footer">
                    <a href="dashboard_admin.php" class="btn-custom btn-cancel">Batal</a>
                    <button type="submit" class="btn-custom btn-submit">＋ Simpan Alat</button>
                </div>

            </div>
        </form>

        <div class="footer-text">
            © 2026 AlatKu — Sistem Informasi Peminjaman Alat
        </div>

    </main>

</body>
</html>
