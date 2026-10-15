<?php
/*
 *   edit_alat.php          -> daftar alat (pilih yang mau diedit)
 *   edit_alat.php?id=5     -> form edit alat
 */
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['admin']);

$kategori = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')->fetchAll();
$kondisiList = ['baik' => 'Baik', 'rusak ringan' => 'Rusak Ringan', 'rusak berat' => 'Rusak Berat'];

$id = (int)($_GET['id'] ?? 0);
$alat = null;
$daftar = [];
$errors = [];

if ($id) {
    $s = $pdo->prepare('SELECT * FROM alat WHERE id_alat = ?');
    $s->execute([$id]);
    $alat = $s->fetch();
    if (!$alat) {
        set_flash('error', 'Data alat tidak ditemukan.');
        redirect('edit_alat.php');
    }
} else {
    $daftar = $pdo->query(
        'SELECT a.id_alat, a.nama_alat, a.stok, a.lokasi, a.kondisi, k.nama_kategori
         FROM alat a LEFT JOIN kategori k ON k.id_kategori = a.id_kategori
         ORDER BY a.nama_alat'
    )->fetchAll();
}

$f = $alat ? [
    'nama_alat' => $alat['nama_alat'], 'id_kategori' => $alat['id_kategori'],
    'lokasi' => $alat['lokasi'], 'stok' => $alat['stok'], 'kondisi' => $alat['kondisi'],
] : [];

if ($alat && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $f = [
        'nama_alat'   => trim($_POST['nama_alat'] ?? ''),
        'id_kategori' => (int)($_POST['id_kategori'] ?? 0),
        'lokasi'      => trim($_POST['lokasi'] ?? ''),
        'stok'        => trim($_POST['stok'] ?? ''),
        'kondisi'     => $_POST['kondisi'] ?? '',
    ];

    if ($f['nama_alat'] === '' || mb_strlen($f['nama_alat']) > 100) {
        $errors[] = 'Nama alat wajib diisi (maksimal 100 karakter).';
    }
    if ($f['lokasi'] === '' || mb_strlen($f['lokasi']) > 100) {
        $errors[] = 'Lokasi wajib diisi (maksimal 100 karakter).';
    }
    if (!ctype_digit($f['stok'])) {
        $errors[] = 'Jumlah stok harus bilangan bulat, minimal 0.';
    }
    if (!isset($kondisiList[$f['kondisi']])) {
        $errors[] = 'Kondisi alat tidak valid.';
    }
    if (!in_array($f['id_kategori'], array_map('intval', array_column($kategori, 'id_kategori')), true)) {
        $errors[] = 'Pilih kategori yang valid.';
    }

    if (!$errors) {
        $pdo->prepare(
            'UPDATE alat SET id_kategori = ?, nama_alat = ?, stok = ?, lokasi = ?, kondisi = ? WHERE id_alat = ?'
        )->execute([$f['id_kategori'], $f['nama_alat'], (int)$f['stok'], $f['lokasi'], $f['kondisi'], $id]);

        set_flash('success', 'Data alat berhasil diperbarui.');
        redirect('edit_alat.php');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Alat | Sistem Peminjaman Alat</title>

    <link rel="stylesheet" href="../asset/css/bootstrap.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --blue-deep: #1e3a8a;
            --blue-light: #dbeafe;
            --bg: #f3f7fc;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Roboto', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .navbar-admin {
            min-height: 82px;
            padding: 14px 5.5%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 4px 22px rgba(30, 64, 175, .07);
            border-bottom: 1px solid #edf2f9;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 13px;
            margin: 0;
        }

        .brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff;
            font-size: 16px;
            font-weight: 800;
            box-shadow: 0 7px 16px rgba(37, 99, 235, .2);
        }

        .brand-text h2 {
            font-size: 17px;
            font-weight: 800;
            margin: 0 0 4px;
            color: #172554;
        }

        .brand-text p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .navbar-profile {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .profile-avatar {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: var(--blue-dark);
            background: var(--blue-light);
            font-weight: 800;
        }

        .profile-info strong {
            display: block;
            font-size: 13px;
            color: var(--text);
        }

        .profile-info span {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        .dashboard-container {
            width: min(1000px, 92%);
            margin: 35px auto 0;
            animation: fadeUp .55s ease both;
        }

        .breadcrumb-custom {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 9px;
            margin-bottom: 24px;
            color: #94a3b8;
            font-size: 13px;
        }

        .breadcrumb-custom a {
            color: var(--blue);
            text-decoration: none;
        }

        .breadcrumb-custom a:hover { text-decoration: underline; }

        .page-heading {
            margin-bottom: 24px;
        }

        .eyebrow {
            display: inline-block;
            color: var(--blue);
            background: #e8f1ff;
            padding: 7px 11px;
            border-radius: 7px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
            margin-bottom: 11px;
        }

        .page-heading h1 {
            font-size: clamp(24px, 4vw, 30px);
            line-height: 1.2;
            margin: 0 0 9px;
            font-weight: 800;
            color: #172554;
        }

        .page-heading p {
            color: var(--muted);
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-card {
            overflow: hidden;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(30, 64, 175, .07);
        }

        .card-heading {
            padding: 24px 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(110deg, #eff6ff, #fff);
            border-bottom: 1px solid var(--border);
        }

        .heading-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            border-radius: 13px;
            background: var(--blue-light);
            color: var(--blue-dark);
            font-size: 19px;
        }

        .card-heading h2 {
            font-size: 16px;
            font-weight: 800;
            margin: 0 0 5px;
        }

        .card-heading p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .form-body { padding: 29px; }

        .form-group { margin-bottom: 22px; }

        .form-label {
            display: block;
            margin-bottom: 9px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .required { color: var(--blue); }

        .form-control,
        .form-select {
            width: 100%;
            min-height: 46px;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background-color: #fff;
            color: var(--text);
            font: 400 13px 'Roboto', sans-serif;
            box-shadow: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #3b82f6;
            outline: 0;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .12);
        }

        .form-control[readonly] {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        textarea.form-control {
            min-height: 105px;
            resize: vertical;
        }

        .form-hint {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-size: 11px;
            line-height: 1.5;
        }

        .upload-box {
            padding: 22px;
            text-align: center;
            background: #f8fbff;
            border: 2px dashed #bfdbfe;
            border-radius: 13px;
            transition: background .2s, border-color .2s;
        }

        .upload-box:hover {
            background: #eff6ff;
            border-color: var(--blue);
        }

        .upload-icon {
            color: var(--blue);
            font-size: 28px;
            margin-bottom: 10px;
        }

        .upload-description {
            color: var(--muted);
            font-size: 12px;
            margin: 0 0 14px;
        }

        .file-input {
            max-width: 100%;
            font-size: 12px;
        }

        #preview {
            display: none;
            max-width: 180px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin: 0 auto 15px;
            border: 1px solid var(--border);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 21px 29px;
            background: #f8fafc;
            border-top: 1px solid var(--border);
        }

        .button-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-custom {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 9px;
            min-height: 43px;
            padding: 11px 17px;
            border-radius: 9px;
            font: 700 12px 'Roboto', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s, background .2s;
        }

        .btn-back {
            color: #475569;
            background: #fff;
            border: 1px solid #cbd5e1;
        }

        .btn-back:hover {
            color: var(--blue-dark);
            background: #eff6ff;
            transform: translateY(-1px);
        }

        .btn-save {
            color: #fff;
            border: 1px solid var(--blue);
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            box-shadow: 0 5px 13px rgba(37, 99, 235, .2);
        }

        .btn-save:hover {
            color: #fff;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            box-shadow: 0 8px 18px rgba(37, 99, 235, .27);
            transform: translateY(-2px);
        }

        .footer {
            text-align: center;
            padding: 25px 10px;
            color: #94a3b8;
            font-size: 12px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .navbar-admin { padding: 13px 4%; }
            .brand-icon { width: 42px; height: 42px; }
            .brand-text h2 { font-size: 13px; }
            .brand-text p { font-size: 10px; }
            .profile-info { display: none; }
            .dashboard-container { width: 92%; margin-top: 25px; }
            .card-heading { padding: 20px; }
            .form-body { padding: 21px 18px; }
            .form-footer { padding: 17px 18px; align-items: stretch; }
            .button-group { flex-direction: column-reverse; align-items: stretch; }
            .btn-custom { padding: 11px 12px; }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<style>
    .pilih-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pilih-table th { text-align: left; padding: 12px 14px; color: #64748b; font-size: 11px;
                      text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
    .pilih-table td { padding: 13px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .pilih-table tr:last-child td { border-bottom: 0; }
    .pilih-table a.btn-pilih { display: inline-block; padding: 7px 14px; border-radius: 9px; background: #e8f1ff;
                      color: var(--blue-dark); font-weight: 600; font-size: 12px; text-decoration: none; }
    .pilih-table a.btn-pilih:hover { background: #dbeafe; }
    .table-scroll { overflow-x: auto; }
</style>

    <main class="dashboard-container">

        <nav class="breadcrumb-custom" aria-label="Breadcrumb">
            <a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <i class="fa-solid fa-chevron-right" style="font-size:10px"></i>
            <a href="lihat_alat.php">Data Alat</a>
            <i class="fa-solid fa-chevron-right" style="font-size:10px"></i>
            <span>Edit Alat</span>
        </nav>

        <section class="page-heading">
            <span class="eyebrow">MANAJEMEN INVENTARIS</span>
            <h1>Edit Data Alat</h1>
            <p>Perbarui informasi inventaris alat agar data sistem tetap akurat dan terorganisasi.</p>
        </section>

<?php if (!$alat): ?>

        <section class="form-card">
            <div class="card-heading">
                <div class="heading-icon"><i class="fa-solid fa-list"></i></div>
                <div>
                    <h2>Pilih Alat</h2>
                    <p>Klik "Edit" pada alat yang ingin diperbarui.</p>
                </div>
            </div>

            <div class="form-body">
                <?php if (!$daftar): ?>
                    <p style="color:var(--muted);margin:0">
                        Belum ada data alat. <a href="tambah_alat.php">Tambah alat sekarang</a>.
                    </p>
                <?php else: ?>
                <div class="table-scroll">
                    <table class="pilih-table">
                        <thead>
                            <tr>
                                <th>Nama Alat</th><th>Kategori</th><th>Stok</th><th>Lokasi</th><th>Kondisi</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($daftar as $a): ?>
                            <tr>
                                <td><strong><?= e($a['nama_alat']) ?></strong><br>
                                    <small style="color:var(--muted)"><?= e(kode('ALT-', (int)$a['id_alat'])) ?></small></td>
                                <td><?= e($a['nama_kategori'] ?? '-') ?></td>
                                <td><?= (int)$a['stok'] ?></td>
                                <td><?= e($a['lokasi']) ?></td>
                                <td><?= e(ucfirst($a['kondisi'])) ?></td>
                                <td><a class="btn-pilih" href="edit_alat.php?id=<?= (int)$a['id_alat'] ?>">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-footer">
                <a href="dashboard_admin.php" class="btn-custom btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
            </div>
        </section>

<?php else: ?>

        <?php if ($errors): ?>
            <div class="nv-alert" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form id="formEditAlat" method="post" action="edit_alat.php?id=<?= (int)$id ?>">
            <?= csrf_field() ?>

            <section class="form-card">

                <div class="card-heading">
                    <div class="heading-icon"><i class="fa-solid fa-pen-to-square"></i></div>
                    <div>
                        <h2>Informasi Alat</h2>
                        <p>Ubah data alat pada kolom di bawah ini.</p>
                    </div>
                </div>

                <div class="form-body">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="id_alat">ID Alat</label>
                                <input class="form-control" type="text" id="id_alat"
                                       value="<?= e(kode('ALT-', (int)$id)) ?>" readonly>
                                <small class="form-hint">ID alat tidak dapat diubah.</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="nama_alat">Nama Alat <span class="required">*</span></label>
                                <input class="form-control" type="text" id="nama_alat" name="nama_alat"
                                       value="<?= e($f['nama_alat']) ?>" maxlength="100"
                                       placeholder="Masukkan nama alat" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="id_kategori">Kategori <span class="required">*</span></label>
                                <select class="form-select" id="id_kategori" name="id_kategori" required>
                                    <option value="">Pilih kategori</option>
                                    <?php foreach ($kategori as $k): ?>
                                        <option value="<?= (int)$k['id_kategori'] ?>"
                                            <?= (int)$f['id_kategori'] === (int)$k['id_kategori'] ? 'selected' : '' ?>>
                                            <?= e($k['nama_kategori']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="stok">Jumlah Stok <span class="required">*</span></label>
                                <input class="form-control" type="number" id="stok" name="stok"
                                       value="<?= e($f['stok']) ?>" min="0"
                                       placeholder="Masukkan jumlah stok" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label" for="lokasi">Lokasi Penyimpanan <span class="required">*</span></label>
                                <input class="form-control" type="text" id="lokasi" name="lokasi"
                                       value="<?= e($f['lokasi']) ?>" maxlength="100" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom:0">
                                <label class="form-label" for="kondisi">Kondisi Alat <span class="required">*</span></label>
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
                    <a href="edit_alat.php" class="btn-custom btn-back">
                        <i class="fa-solid fa-arrow-left"></i> Kembali
                    </a>

                    <div class="button-group">
                        <button type="reset" class="btn-custom btn-back">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </button>
                        <button type="submit" class="btn-custom btn-save">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>

            </section>
        </form>

<?php endif; ?>

        <footer class="footer">
            &copy; 2026 Sistem Peminjaman Alat. Semua hak dilindungi.
        </footer>

    </main>

</body>
</html>
