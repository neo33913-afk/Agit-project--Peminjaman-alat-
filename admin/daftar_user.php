<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['admin']);

/* ---- Hapus user ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $idHapus = (int)($_POST['id'] ?? 0);

    if ($idHapus === (int)$me['id']) {
        set_flash('error', 'Akun yang sedang kamu pakai tidak dapat dihapus.');
    } else {
        try {
            $s = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $s->execute([$idHapus]);
            if ($s->rowCount()) {
                set_flash('success', 'User berhasil dihapus.');
            } else {
                set_flash('error', 'User tidak ditemukan.');
            }
        } catch (PDOException $e) {
            set_flash('error', 'User tidak bisa dihapus karena masih memiliki riwayat peminjaman.');
        }
    }
    redirect('daftar_user.php');
}

/* ---- Data ---- */
$users = $pdo->query(
    "SELECT id, username, nama_lengkap, alamat, role
     FROM users
     ORDER BY FIELD(role, 'admin', 'petugas', 'peminjam'), nama_lengkap"
)->fetchAll();

$hitung = ['admin' => 0, 'petugas' => 0, 'peminjam' => 0];
foreach ($users as $u) {
    if (isset($hitung[$u['role']])) {
        $hitung[$u['role']]++;
    }
}

$rolePilihan = in_array($_GET['role'] ?? '', ['admin', 'petugas', 'peminjam'], true) ? $_GET['role'] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Daftar User | AlatKu</title>

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

        .container-user {
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

        .icon-admin {
            background: #ede9fe;
        }

        .icon-petugas {
            background: #dcfce7;
        }

        .icon-user {
            background: #fff4d9;
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
            min-height: 42px;
            padding: 10px 13px;
            border: 1px solid #d7e5ff;
            background: #fbfdff;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 11px;
            outline: none;
        }

        .filter-area input:focus,
        .filter-area select:focus {
            border-color: #3298ff;
        }

        .btn-tambah {
            display: inline-block;
            background: linear-gradient(135deg, #0869d8, #3298ff);
            color: white;
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            transition: .3s;
        }

        .btn-tambah:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(0, 105, 216, .2);
        }

        .table-responsive {
            border: 1px solid #e5edf8;
            border-radius: 12px;
        }

        .table {
            min-width: 900px;
            margin-bottom: 0;
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
            padding: 15px 14px;
            border-bottom: 1px solid #edf2fa;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .table tbody tr:hover {
            background: #fafcff;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 180px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0870e8, #3298ff);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .user-name {
            color: #123f75;
            font-size: 11px;
            font-weight: 600;
        }

        .user-email {
            font-size: 10px;
            color: #91a2bd;
            margin-top: 4px;
        }

        .role {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .role-admin {
            background: #ede9fe;
            color: #6d28d9;
        }

        .role-petugas {
            background: #dcfce7;
            color: #15803d;
        }

        .role-user {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-aktif {
            color: #15803d;
            background: #dcfce7;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .status-nonaktif {
            color: #dc2626;
            background: #fee2e2;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .aksi {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .btn-edit,
        .btn-hapus {
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .btn-edit {
            color: #0870d9;
            background: #edf5ff;
            border: 1px solid #d7e5ff;
        }

        .btn-edit:hover {
            background: #dbeafe;
        }

        .btn-hapus {
            color: #dc2626;
            background: #fff1f2;
            border: 1px solid #fecdd3;
        }

        .btn-hapus:hover {
            background: #ffe4e6;
        }

        .empty-state {
            display: none;
            text-align: center;
            padding: 35px 15px;
            color: #8192ad;
            font-size: 12px;
        }

        .footer-info {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
            color: #8192ad;
            font-size: 11px;
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
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<style>
    .aksi a.btn-edit { display: inline-block; text-decoration: none; }
    .aksi form { display: inline; margin: 0; }
    .role-peminjam { background: #fff4d9; color: #a86b00; }
</style>

<div class="nv-page">
<div class="container-user">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="page-title">Daftar User</h2>
            <div class="breadcrumb-text mb-0">
                Dashboard / Manajemen User / Daftar User
            </div>
        </div>

        <a href="dashboard_admin.php" class="btn-kembali">
            ← Kembali
        </a>
    </div>

    <!-- STATISTIK -->
    <div class="row g-3">

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-total">👥</div>
                <div class="stat-label">Total User</div>
                <div class="stat-number"><?= count($users) ?></div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-admin">🛡️</div>
                <div class="stat-label">Admin</div>
                <div class="stat-number"><?= $hitung['admin'] ?></div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-petugas">🧑‍💼</div>
                <div class="stat-label">Petugas</div>
                <div class="stat-number"><?= $hitung['petugas'] ?></div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon icon-user">🙋</div>
                <div class="stat-label">User Peminjam</div>
                <div class="stat-number"><?= $hitung['peminjam'] ?></div>
            </div>
        </div>

    </div>

    <!-- TABEL USER -->
    <div class="main-card">

        <div class="main-card-header">
            <div>
                <h4>Data Pengguna</h4>
                <p>Kelola informasi akun admin, petugas, dan peminjam.</p>
            </div>

            <a href="edit_user.php?tambah=1" class="btn-tambah">
                + Tambah User
            </a>
        </div>

        <!-- PENCARIAN -->
        <div class="filter-area mb-4">
            <input type="text" id="cariUser"
                   placeholder="Cari nama atau username..."
                   style="min-width: 230px;">

            <select id="filterRole">
                <option value="">Semua Role</option>
                <option value="admin" <?= $rolePilihan === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="petugas" <?= $rolePilihan === 'petugas' ? 'selected' : '' ?>>Petugas</option>
                <option value="peminjam" <?= $rolePilihan === 'peminjam' ? 'selected' : '' ?>>Peminjam</option>
            </select>
        </div>

        <!-- TABEL -->
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Pengguna</th>
                        <th>Username</th>
                        <th>Alamat</th>
                        <th>Role</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody id="tabelUser">
                <?php foreach ($users as $u): ?>
                    <?php
                    $nama   = $u['nama_lengkap'] ?: $u['username'];
                    $editPg = $u['role'] === 'petugas' ? 'edit_petugas.php' : 'edit_user.php';
                    $roleCls = $u['role'] === 'peminjam' ? 'role-user' : 'role-' . $u['role'];
                    ?>
                    <tr class="data-user"
                        data-cari="<?= e(mb_strtolower($nama . ' ' . $u['username'])) ?>"
                        data-role="<?= e($u['role']) ?>">

                        <td><?= e(kode('USR', (int)$u['id'])) ?></td>

                        <td>
                            <div class="user-profile">
                                <div class="avatar"><?= e(mb_strtoupper(mb_substr($nama, 0, 1))) ?></div>
                                <div>
                                    <div class="user-name"><?= e($nama) ?></div>
                                    <div class="user-email"><?= e(role_label($u['role'])) ?></div>
                                </div>
                            </div>
                        </td>

                        <td><?= e($u['username']) ?></td>
                        <td><?= e($u['alamat'] ?: '-') ?></td>

                        <td><span class="role <?= e($roleCls) ?> role-<?= e($u['role']) ?>"><?= e(role_label($u['role'])) ?></span></td>

                        <td>
                            <div class="aksi">
                                <a class="btn-edit" href="<?= e($editPg) ?>?id=<?= (int)$u['id'] ?>">Edit</a>

                                <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                <form method="post"
                                      onsubmit="return confirm('Apakah kamu yakin ingin menghapus user <?= e(addslashes($nama)) ?>?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn-hapus">Hapus</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="pesanKosong">
            <div style="font-size:35px; margin-bottom:10px;">🔍</div>
            <strong>User tidak ditemukan</strong>
            <p class="mt-2 mb-0">
                Coba ubah kata kunci atau filter pencarian.
            </p>
        </div>

        <div class="footer-info">
            <span id="jumlahData">Menampilkan <?= count($users) ?> pengguna</span>
            <span>AlatKu &copy; 2026</span>
        </div>

    </div>

</div>
</div>

<script>
    const cariUser = document.getElementById('cariUser');
    const filterRole = document.getElementById('filterRole');

    function filterUser() {
        const kataKunci = cariUser.value.toLowerCase().trim();
        const roleDipilih = filterRole.value;
        let jumlahTampil = 0;

        document.querySelectorAll('.data-user').forEach(function (row) {
            const cocokKata = row.dataset.cari.includes(kataKunci);
            const cocokRole = roleDipilih === '' || row.dataset.role === roleDipilih;

            if (cocokKata && cocokRole) {
                row.style.display = '';
                jumlahTampil++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('pesanKosong').style.display =
            jumlahTampil === 0 ? 'block' : 'none';

        document.getElementById('jumlahData').textContent =
            'Menampilkan ' + jumlahTampil + ' pengguna';
    }

    cariUser.addEventListener('input', filterUser);
    filterRole.addEventListener('change', filterUser);
    filterUser();
</script>

</body>
</html>
