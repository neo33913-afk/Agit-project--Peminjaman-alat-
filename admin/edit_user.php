<?php
/*
 * Tambah / edit user. Juga dipakai oleh edit_petugas.php
 * (yang mengisi $lockRole = 'petugas' sebelum meng-include file ini).
 *   edit_user.php?id=5       -> edit user
 *   edit_user.php?tambah=1   -> tambah user baru
 */
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['admin']);

$lockRole = $lockRole ?? null;
$tambah   = isset($_GET['tambah']);
$id       = (int)($_GET['id'] ?? 0);
$user     = null;
$self     = basename($_SERVER['SCRIPT_NAME']);

if (!$tambah) {
    $s = $pdo->prepare('SELECT id, username, nama_lengkap, alamat, role FROM users WHERE id = ?');
    $s->execute([$id]);
    $user = $s->fetch();

    if (!$user) {
        set_flash('error', 'User tidak ditemukan.');
        redirect('daftar_user.php');
    }
    if ($lockRole && $user['role'] !== $lockRole) {
        redirect('edit_user.php?id=' . $id);
    }
}

$form = [
    'nama_lengkap' => $user['nama_lengkap'] ?? '',
    'username'     => $user['username'] ?? '',
    'alamat'       => $user['alamat'] ?? '',
    'role'         => $user['role'] ?? ($lockRole ?: 'peminjam'),
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $form['nama_lengkap'] = trim($_POST['nama_lengkap'] ?? '');
    $form['username']     = trim($_POST['username'] ?? '');
    $form['alamat']       = trim($_POST['alamat'] ?? '');
    $form['role']         = $lockRole ?: ($_POST['role'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $konf  = $_POST['konfirmasi_password'] ?? '';

    if ($form['nama_lengkap'] === '' || mb_strlen($form['nama_lengkap']) > 100) {
        $errors[] = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $form['username'])) {
        $errors[] = 'Username 3–50 karakter, hanya huruf, angka, titik, atau underscore.';
    }
    if (mb_strlen($form['alamat']) > 225) {
        $errors[] = 'Alamat maksimal 225 karakter.';
    }
    if (!in_array($form['role'], ['admin', 'petugas', 'peminjam'], true)) {
        $errors[] = 'Role tidak valid.';
    }
    if ($tambah && $pass === '') {
        $errors[] = 'Password wajib diisi untuk user baru.';
    }
    if ($pass !== '') {
        if (strlen($pass) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        if ($pass !== $konf) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
    }
    if (!$tambah && (int)$user['id'] === (int)$me['id'] && $form['role'] !== $user['role']) {
        $errors[] = 'Kamu tidak bisa mengubah role akunmu sendiri.';
    }

    if (!$errors) {
        $cek = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $cek->execute([$form['username'], $tambah ? 0 : $id]);
        if ($cek->fetch()) {
            $errors[] = 'Username sudah dipakai pengguna lain.';
        }
    }

    if (!$errors) {
        if ($tambah) {
            $pdo->prepare(
                'INSERT INTO users (username, nama_lengkap, alamat, password, role) VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $form['username'], $form['nama_lengkap'], $form['alamat'],
                password_hash($pass, PASSWORD_DEFAULT), $form['role'],
            ]);
            set_flash('success', 'User baru berhasil ditambahkan.');
        } else {
            if ($pass !== '') {
                $pdo->prepare(
                    'UPDATE users SET username = ?, nama_lengkap = ?, alamat = ?, role = ?, password = ? WHERE id = ?'
                )->execute([
                    $form['username'], $form['nama_lengkap'], $form['alamat'], $form['role'],
                    password_hash($pass, PASSWORD_DEFAULT), $id,
                ]);
            } else {
                $pdo->prepare(
                    'UPDATE users SET username = ?, nama_lengkap = ?, alamat = ?, role = ? WHERE id = ?'
                )->execute([
                    $form['username'], $form['nama_lengkap'], $form['alamat'], $form['role'], $id,
                ]);
            }
            set_flash('success', 'Data pengguna berhasil diperbarui.');
        }
        redirect('daftar_user.php' . ($form['role'] === 'petugas' ? '?role=petugas' : ''));
    }
}

$jenis      = $lockRole === 'petugas' ? 'Petugas' : 'User';
$judulHal   = ($tambah ? 'Tambah ' : 'Edit ') . $jenis;
$formAction = $self . ($tambah ? '?tambah=1' : '?id=' . $id);
$namaPreview = $form['nama_lengkap'] !== '' ? $form['nama_lengkap'] : 'Nama Pengguna';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit User | Sistem Peminjaman Alat</title>

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
            padding: 35px 20px;
        }

        .container-page {
            max-width: 900px;
            margin: auto;
        }

        /* HEADER */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 28px;
        }

        .header h2 {
            font-size: 25px;
            font-weight: 700;
            color: #073b78;
            margin-bottom: 7px;
        }

        .header p {
            font-size: 12px;
            color: #8090aa;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 17px;
            background: #fff;
            color: #176bc1;
            border: 1px solid #dce8fa;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: .25s;
        }

        .btn-back:hover {
            background: #e8f2ff;
            color: #0864cf;
            transform: translateX(-3px);
        }

        /* FORM CARD */
        .form-card {
            background: #fff;
            border: 1px solid #dce8fa;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 8px 25px rgba(27, 82, 150, .06);
            animation: fadeIn .5s ease;
        }

        .icon-box {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, #0864cf, #3196ff);
            color: #fff;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 27px;
            margin-bottom: 18px;
            box-shadow: 0 6px 15px rgba(25, 117, 224, .2);
        }

        .form-card h3 {
            font-size: 19px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 12px;
            color: #8090aa;
            margin-bottom: 28px;
            line-height: 1.7;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #123b70;
            padding-bottom: 13px;
            margin-bottom: 22px;
            border-bottom: 1px solid #e8eff9;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #344e72;
            margin-bottom: 9px;
        }

        .required {
            color: #ef4444;
        }

        .form-control-custom {
            width: 100%;
            height: 46px;
            padding: 0 14px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            color: #294565;
            background: #fbfdff;
            border: 1px solid #dce6f3;
            border-radius: 10px;
            outline: none;
            transition: .25s;
        }

        .form-control-custom:focus {
            background: #fff;
            border-color: #2585ed;
            box-shadow: 0 0 0 3px rgba(37, 133, 237, .1);
        }

        .form-control-custom:disabled {
            background: #f0f4fa;
            color: #8191a9;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .help-text {
            margin-top: 7px;
            color: #8998ad;
            font-size: 10px;
            line-height: 1.7;
        }

        /* INFORMASI AKUN */
        .account-info {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 17px;
            margin-bottom: 25px;
            background: #f4f8ff;
            border: 1px solid #e1ebfa;
            border-radius: 13px;
        }

        .avatar {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #0864cf, #3196ff);
            color: #fff;
            font-size: 19px;
            font-weight: 700;
        }

        .account-info h4 {
            font-size: 13px;
            margin-bottom: 5px;
            color: #123b70;
        }

        .account-info p {
            font-size: 11px;
            color: #8090aa;
        }

        .account-badge {
            margin-left: auto;
            background: #e6f9ef;
            color: #16864a;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        /* BUTTON */
        .button-group {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 12px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #e8eff9;
        }

        .btn-custom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 21px;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: .25s;
        }

        .btn-cancel {
            background: #fff;
            color: #71819b;
            border: 1px solid #dce6f3;
        }

        .btn-cancel:hover {
            background: #f1f6fd;
        }

        .btn-save {
            background: linear-gradient(135deg, #0864cf, #278dff);
            color: #fff;
            border: none;
            box-shadow: 0 5px 12px rgba(25, 117, 224, .18);
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(25, 117, 224, .28);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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

            .form-card {
                padding: 22px 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .account-badge {
                display: none;
            }

            .button-group {
                flex-direction: column-reverse;
            }

            .btn-custom {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="nv-page">
<div class="container-page">

    <!-- HEADER -->
    <div class="header">
        <div>
            <h2><?= e($judulHal) ?></h2>
            <p>Dashboard / Data <?= e($jenis) ?> / <?= e($judulHal) ?></p>
        </div>

        <a href="daftar_user.php<?= $lockRole === 'petugas' ? '?role=petugas' : '' ?>" class="btn-back">
            &larr; Kembali
        </a>
    </div>

    <!-- FORM -->
    <div class="form-card">

        <div class="icon-box"><?= $tambah ? '+' : '&#9998;' ?></div>

        <h3><?= $tambah ? 'Tambah ' . e($jenis) . ' Baru' : 'Edit Informasi ' . e($jenis) ?></h3>
        <p class="subtitle">
            <?= $tambah
                ? 'Isi data akun baru. Pastikan username unik dan password minimal 8 karakter.'
                : 'Perbarui informasi pengguna sesuai dengan data terbaru. Pastikan seluruh data sudah benar sebelum disimpan.' ?>
        </p>

        <?php if ($errors): ?>
            <div class="nv-alert" role="alert">
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- RINGKASAN -->
        <div class="account-info">
            <div class="avatar" id="avatar"><?= e(mb_strtoupper(mb_substr($namaPreview, 0, 1))) ?></div>

            <div>
                <h4 id="previewNama"><?= e($namaPreview) ?></h4>
                <p id="previewUser">@<?= e($form['username'] ?: 'username') ?></p>
            </div>

            <span class="account-badge"><?= e(role_label($form['role'])) ?></span>
        </div>

        <form id="formEditUser" method="post" action="<?= e($formAction) ?>">
            <?= csrf_field() ?>

            <div class="section-title">Informasi Pribadi</div>

            <div class="form-group">
                <label for="nama">Nama Lengkap <span class="required">*</span></label>
                <input type="text" id="nama" name="nama_lengkap"
                       class="form-control-custom"
                       value="<?= e($form['nama_lengkap']) ?>"
                       placeholder="Masukkan nama lengkap" maxlength="100" required>
            </div>

            <div class="form-group">
                <label for="username">Username <span class="required">*</span></label>
                <input type="text" id="username" name="username"
                       class="form-control-custom"
                       value="<?= e($form['username']) ?>"
                       placeholder="Masukkan username" maxlength="50" required>
            </div>

            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" class="form-control-custom"
                          style="height:100px;padding-top:13px;resize:vertical"
                          maxlength="225"
                          placeholder="Masukkan alamat lengkap"><?= e($form['alamat']) ?></textarea>
            </div>

            <div class="section-title">Pengaturan Akun</div>

            <div class="form-group">
                <label for="role">Role Pengguna <span class="required">*</span></label>

                <?php if ($lockRole): ?>
                    <input type="text" class="form-control-custom" value="<?= e(role_label($lockRole)) ?>" disabled>
                <?php else: ?>
                    <select id="role" name="role" class="form-control-custom" required>
                        <option value="peminjam" <?= $form['role'] === 'peminjam' ? 'selected' : '' ?>>Peminjam</option>
                        <option value="petugas" <?= $form['role'] === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                        <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                <?php endif; ?>

                <p class="help-text">Role menentukan hak akses dan menu yang tampil di navbar.</p>
            </div>

            <div class="section-title"><?= $tambah ? 'Password' : 'Ganti Password' ?></div>

            <div class="form-row">

                <div class="form-group">
                    <label for="password">
                        Password <?= $tambah ? '<span class="required">*</span>' : 'Baru' ?>
                    </label>
                    <input type="password" id="password" name="password"
                           class="form-control-custom"
                           placeholder="<?= $tambah ? 'Minimal 8 karakter' : 'Kosongkan jika tidak diubah' ?>"
                           minlength="8" autocomplete="new-password"
                           <?= $tambah ? 'required' : '' ?>>
                    <p class="help-text">Minimal 8 karakter.</p>
                </div>

                <div class="form-group">
                    <label for="konfirmasi_password">Konfirmasi Password</label>
                    <input type="password" id="konfirmasi_password" name="konfirmasi_password"
                           class="form-control-custom"
                           placeholder="Ulangi password"
                           minlength="8" autocomplete="new-password">
                </div>

            </div>

            <!-- TOMBOL -->
            <div class="button-group">
                <a href="daftar_user.php<?= $lockRole === 'petugas' ? '?role=petugas' : '' ?>"
                   class="btn-custom btn-cancel">Batal</a>

                <button type="submit" class="btn-custom btn-save">
                    &#10003; <?= $tambah ? 'Simpan User' : 'Simpan Perubahan' ?>
                </button>
            </div>

        </form>

    </div>

</div>
</div>

<script>
    document.getElementById('nama').addEventListener('input', function () {
        document.getElementById('previewNama').textContent = this.value || 'Nama Pengguna';
        document.getElementById('avatar').textContent =
            (this.value.trim().charAt(0) || 'U').toUpperCase();
    });

    document.getElementById('username').addEventListener('input', function () {
        document.getElementById('previewUser').textContent = '@' + (this.value || 'username');
    });

    document.getElementById('formEditUser').addEventListener('submit', function (event) {
        const password = document.getElementById('password').value;
        const konfirmasi = document.getElementById('konfirmasi_password').value;

        if (password !== konfirmasi) {
            event.preventDefault();
            alert('Konfirmasi password tidak cocok!');
        }
    });
</script>

</body>
</html>
