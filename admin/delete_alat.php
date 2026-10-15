<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role($pdo, ['admin']);

/* ---- Proses hapus ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $ids = array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    $terhapus = 0;
    $tertahan = [];

    $cekPakai = $pdo->prepare('SELECT COUNT(*) FROM detail_peminjaman WHERE id_alat = ?');
    $namaStmt = $pdo->prepare('SELECT nama_alat FROM alat WHERE id_alat = ?');
    $hapus    = $pdo->prepare('DELETE FROM alat WHERE id_alat = ?');

    foreach ($ids as $idAlat) {
        $cekPakai->execute([$idAlat]);
        $namaStmt->execute([$idAlat]);
        $nama = $namaStmt->fetchColumn();

        if ($nama === false) {
            continue;
        }
        if ((int)$cekPakai->fetchColumn() > 0) {
            $tertahan[] = $nama;
            continue;
        }
        $hapus->execute([$idAlat]);
        $terhapus += $hapus->rowCount();
    }

    if ($tertahan) {
        set_flash(
            'error',
            ($terhapus ? $terhapus . ' alat dihapus. ' : '') .
            'Tidak bisa dihapus karena pernah dipinjam: ' . implode(', ', $tertahan) . '.'
        );
    } elseif ($terhapus) {
        set_flash('success', $terhapus . ' data alat berhasil dihapus.');
    } else {
        set_flash('error', 'Tidak ada alat yang dihapus.');
    }
    redirect('delete_alat.php');
}

/* ---- Data ---- */
$daftar = $pdo->query(
    'SELECT a.id_alat, a.nama_alat, a.stok, a.kondisi, k.nama_kategori,
            (SELECT COUNT(*) FROM detail_peminjaman d WHERE d.id_alat = a.id_alat) AS dipakai
     FROM alat a LEFT JOIN kategori k ON k.id_kategori = a.id_kategori
     ORDER BY a.nama_alat'
)->fetchAll();

$jumlahTertaut = count(array_filter($daftar, fn($a) => (int)$a['dipakai'] > 0));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Alat | Sistem Peminjaman Alat</title>

    <link rel="stylesheet" href="../asset/css/bootstrap.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --blue-light: #dbeafe;
            --navy: #1e3a8a;
            --background: #f3f7fc;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #dc2626;
            --danger-light: #fee2e2;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Roboto', sans-serif;
            background: var(--background);
            color: var(--text);
        }

        .navbar-admin {
            min-height: 78px;
            background: #fff;
            padding: 14px 5.5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 22px rgba(30, 64, 175, .07);
            position: relative;
            z-index: 2;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            box-shadow: 0 6px 15px rgba(37, 99, 235, .2);
        }

        .navbar-brand h2 {
            margin: 0 0 4px;
            font-size: 17px;
            font-weight: 800;
            color: #1e3a8a;
        }

        .navbar-brand p {
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
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: var(--blue-dark);
            background: var(--blue-light);
            font-weight: 800;
        }

        .profile-info strong, .profile-info span { display: block; }
        .profile-info strong { font-size: 13px; }
        .profile-info span { margin-top: 3px; color: var(--muted); font-size: 11px; }

        .dashboard-container {
            max-width: 1180px;
            margin: 34px auto 0;
            padding: 0 24px;
            animation: fadeUp .55s ease both;
        }

        .breadcrumb-custom {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            align-items: center;
            margin-bottom: 23px;
            color: #94a3b8;
            font-size: 13px;
        }

        .breadcrumb-custom a {
            color: var(--blue);
            text-decoration: none;
        }
        .breadcrumb-custom a:hover { text-decoration: underline; }

        .page-heading {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .heading-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--blue);
            background: #e8f1ff;
            border: 1px solid #d5e5ff;
            border-radius: 30px;
            padding: 7px 11px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            margin-bottom: 12px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            font-size: clamp(24px, 3vw, 31px);
            letter-spacing: -.6px;
            font-weight: 800;
        }

        .page-heading p {
            margin: 0;
            max-width: 620px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 290px;
            gap: 22px;
            align-items: start;
        }

        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 19px;
            box-shadow: 0 10px 32px rgba(37, 99, 235, .055);
            overflow: hidden;
        }

        .panel-header {
            padding: 22px 25px;
            display: flex;
            align-items: center;
            gap: 13px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(110deg, #eff6ff, #fff);
        }

        .panel-icon {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: var(--blue-light);
            color: var(--blue);
            font-size: 18px;
        }

        .panel-header h2 {
            margin: 0 0 5px;
            font-size: 16px;
            font-weight: 800;
        }

        .panel-header p {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
        }

        .panel-body { padding: 24px 25px 27px; }

        .search-row {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
        }

        .search-box {
            position: relative;
            flex: 1;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .form-control, .form-select {
            width: 100%;
            min-height: 45px;
            border: 1px solid #cbd5e1;
            border-radius: 11px;
            padding: 11px 13px;
            color: var(--text);
            background: #fff;
            font: inherit;
            font-size: 13px;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-box .form-control { padding-left: 40px; }

        .form-control:focus, .form-select:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .12);
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 12px 16px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s, background .2s;
            white-space: nowrap;
        }

        .btn:hover { transform: translateY(-2px); }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            box-shadow: 0 5px 13px rgba(37, 99, 235, .18);
        }
        .btn-primary:hover { color: #fff; box-shadow: 0 8px 18px rgba(37, 99, 235, .25); }

        .btn-outline {
            color: #475569;
            background: #fff;
            border: 1px solid #cbd5e1;
        }
        .btn-outline:hover { color: var(--blue); background: #eff6ff; }

        .btn-danger {
            color: #fff;
            background: linear-gradient(135deg, #ef4444, #b91c1c);
            box-shadow: 0 5px 13px rgba(220, 38, 38, .16);
        }
        .btn-danger:hover { box-shadow: 0 8px 18px rgba(220, 38, 38, .23); }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 620px;
        }

        thead { background: #f1f6ff; }
        th {
            padding: 14px 15px;
            text-align: left;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .45px;
            white-space: nowrap;
        }

        td {
            padding: 15px;
            border-top: 1px solid #edf1f7;
            font-size: 12px;
            vertical-align: middle;
        }

        tbody tr { transition: background .2s; }
        tbody tr:hover { background: #f8fbff; }

        .item-name { font-weight: 700; color: #1e293b; }
        .item-id { color: #94a3b8; font-size: 11px; margin-top: 5px; }

        .category-badge, .condition-badge {
            display: inline-flex;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .category-badge { color: #1d4ed8; background: #dbeafe; }
        .condition-good { color: #15803d; background: #dcfce7; }
        .condition-warn { color: #b45309; background: #fef3c7; }
        .condition-bad { color: #b91c1c; background: #fee2e2; }

        .select-cell { width: 38px; }
        input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--blue);
            cursor: pointer;
        }

        .table-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 17px;
            color: var(--muted);
            font-size: 12px;
        }

        .side-panel { padding: 23px; }
        .side-icon {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            color: #1d4ed8;
            background: #dbeafe;
            font-size: 23px;
            margin-bottom: 17px;
        }

        .side-panel h3 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: 800;
        }

        .side-panel > p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.8;
            margin: 0 0 18px;
        }

        .warning-box {
            padding: 14px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            color: #9a3412;
            font-size: 11px;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .warning-box i { margin-right: 5px; }

        .side-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 13px 0;
            border-top: 1px solid var(--border);
            font-size: 12px;
        }

        .side-stat span { color: var(--muted); }
        .side-stat strong { color: var(--text); }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 24px 0 30px;
        }

        .footer-note { color: var(--muted); font-size: 11px; }

        .empty-state {
            text-align: center;
            padding: 32px 15px;
            color: var(--muted);
        }
        .empty-state i {
            display: block;
            color: #93c5fd;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .toast-message {
            display: none;
            margin-top: 15px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            font-size: 12px;
        }

        .modal-backdrop-custom {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10;
            background: rgba(15, 23, 42, .48);
            padding: 20px;
            align-items: center;
            justify-content: center;
        }

        .confirm-modal {
            width: 100%;
            max-width: 410px;
            background: #fff;
            border-radius: 20px;
            padding: 27px;
            box-shadow: 0 25px 70px rgba(15, 23, 42, .25);
            animation: popIn .2s ease;
        }

        .confirm-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--danger-light);
            color: var(--danger);
            font-size: 23px;
        }

        .confirm-modal h3 {
            text-align: center;
            margin: 0 0 9px;
            font-size: 19px;
            font-weight: 800;
        }

        .confirm-modal p {
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .modal-actions { display: flex; gap: 10px; }
        .modal-actions .btn { flex: 1; }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(.96); }
            to { opacity: 1; transform: scale(1); }
        }

        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
            .side-panel { order: -1; }
        }

        @media (max-width: 600px) {
            .navbar-admin { padding: 13px 17px; }
            .navbar-brand h2 { font-size: 13px; }
            .navbar-brand p { font-size: 10px; }
            .brand-icon { width: 39px; height: 39px; }
            .profile-info { display: none; }
            .dashboard-container { padding: 0 14px; margin-top: 24px; }
            .page-heading { align-items: flex-start; }
            .page-heading p { font-size: 12px; }
            .panel-header, .panel-body { padding: 19px 16px; }
            .search-row { flex-direction: column; }
            .search-row .btn { width: 100%; }
            .footer-actions { align-items: stretch; flex-direction: column-reverse; }
            .footer-actions .btn { width: 100%; }
            .table-bottom { align-items: flex-start; flex-direction: column; }
        }
    </style>
    <link rel="stylesheet" href="../asset/css/navbar.css">
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard-container">

        <nav class="breadcrumb-custom" aria-label="breadcrumb">
            <a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <span>/</span>
            <a href="lihat_alat.php">Data Alat</a>
            <span>/</span>
            <span>Delete Alat</span>
        </nav>

        <section class="page-heading">
            <div>
                <span class="heading-label"><i class="fa-solid fa-boxes-stacked"></i> MANAJEMEN INVENTARIS</span>
                <h1>Hapus Data Alat</h1>
                <p>Periksa data inventaris dan pilih alat yang sudah tidak digunakan untuk dihapus dari daftar.</p>
            </div>
        </section>

        <section class="content-grid">

            <div class="panel">
                <div class="panel-header">
                    <div class="panel-icon"><i class="fa-solid fa-trash-can"></i></div>
                    <div>
                        <h2>Daftar Inventaris Alat</h2>
                        <p>Pilih satu atau beberapa alat yang ingin dihapus.</p>
                    </div>
                </div>

                <div class="panel-body">

                    <form method="post" action="delete_alat.php" id="formHapus">
                    <?= csrf_field() ?>

                    <div class="search-row">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="searchInput" class="form-control"
                                   placeholder="Cari nama alat, ID, atau kategori...">
                        </div>
                        <button type="button" class="btn btn-primary" onclick="cariAlat()">
                            <i class="fa-solid fa-search"></i> Cari Alat
                        </button>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th class="select-cell"><input type="checkbox" id="selectAll" aria-label="Pilih semua alat"></th>
                                    <th>Nama Alat</th>
                                    <th>Kategori</th>
                                    <th>Stok</th>
                                    <th>Kondisi</th>
                                </tr>
                            </thead>

                            <tbody id="alatTable">
                            <?php foreach ($daftar as $a): ?>
                                <?php
                                $kls = $a['kondisi'] === 'baik' ? 'condition-good' : 'condition-warn';
                                ?>
                                <tr data-nama="<?= e($a['nama_alat']) ?>">
                                    <td>
                                        <input type="checkbox" class="alat-check" name="ids[]"
                                               value="<?= (int)$a['id_alat'] ?>"
                                               aria-label="Pilih <?= e($a['nama_alat']) ?>">
                                    </td>
                                    <td>
                                        <div class="item-name"><?= e($a['nama_alat']) ?></div>
                                        <div class="item-id">
                                            <?= e(kode('ALT-', (int)$a['id_alat'])) ?>
                                            <?php if ((int)$a['dipakai'] > 0): ?>
                                                · pernah dipinjam
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="category-badge"><?= e($a['nama_kategori'] ?? '-') ?></span></td>
                                    <td><?= (int)$a['stok'] ?> unit</td>
                                    <td><span class="condition-badge <?= $kls ?>"><?= e(ucfirst($a['kondisi'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$daftar): ?>
                                <tr><td colspan="5" style="text-align:center;color:#64748b">Belum ada data alat.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-bottom">
                        <span id="selectedInfo">0 alat dipilih</span>

                        <button type="button" class="btn btn-danger" id="deleteSelectedBtn"
                                onclick="konfirmasiHapus()" disabled>
                            <i class="fa-solid fa-trash-can"></i> Hapus yang Dipilih
                        </button>
                    </div>

                    </form>
                </div>
            </div>

            <aside class="panel side-panel">
                <div class="side-icon"><i class="fa-solid fa-shield-halved"></i></div>

                <h3>Perhatian Sebelum Menghapus</h3>
                <p>Pastikan alat yang dipilih benar sebelum melanjutkan penghapusan data.</p>

                <div class="warning-box">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Alat yang pernah tercatat dalam transaksi peminjaman tidak dapat dihapus
                    agar riwayat dan laporan tetap utuh.
                </div>

                <div class="side-stat">
                    <span>Total data alat</span>
                    <strong id="totalAlat"><?= count($daftar) ?> alat</strong>
                </div>

                <div class="side-stat">
                    <span>Dipilih untuk dihapus</span>
                    <strong id="totalDipilih">0 alat</strong>
                </div>

                <div class="side-stat">
                    <span>Tertaut transaksi</span>
                    <strong style="color:#2563eb"><?= $jumlahTertaut ?> alat</strong>
                </div>
            </aside>

        </section>

        <div class="footer-actions">
            <span class="footer-note">
                <i class="fa-solid fa-circle-info"></i> Penghapusan bersifat permanen.
            </span>

            <a href="dashboard_admin.php" class="btn btn-outline">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>

    </main>

    <div class="modal-backdrop-custom" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="confirm-modal">
            <div class="confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
            <h3 id="modalTitle">Hapus data alat?</h3>
            <p id="modalText">Data yang dipilih akan dihapus.</p>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="tutupModal()">Batal</button>
                <button type="button" class="btn btn-danger" onclick="kirimHapus()">
                    <i class="fa-solid fa-trash-can"></i> Ya, Hapus
                </button>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const selectAll = document.getElementById('selectAll');
        const deleteButton = document.getElementById('deleteSelectedBtn');
        const tableBody = document.getElementById('alatTable');
        const confirmModal = document.getElementById('confirmModal');

        function barisData() {
            return [...tableBody.querySelectorAll('tr[data-nama]')];
        }

        function barisTerlihat() {
            return barisData().filter(row => row.style.display !== 'none');
        }

        function barisTerpilih() {
            return [...tableBody.querySelectorAll('.alat-check:checked')]
                .map(checkbox => checkbox.closest('tr'));
        }

        function perbaruiPilihan() {
            const selected = barisTerpilih().length;

            document.getElementById('selectedInfo').textContent = selected + ' alat dipilih';
            document.getElementById('totalDipilih').textContent = selected + ' alat';

            deleteButton.disabled = selected === 0;
            deleteButton.style.opacity = selected === 0 ? '.55' : '1';

            const visible = barisTerlihat();
            selectAll.checked = visible.length > 0 &&
                visible.every(row => row.querySelector('.alat-check').checked);
            selectAll.indeterminate = visible.some(row => row.querySelector('.alat-check').checked) &&
                !selectAll.checked;
        }

        function cariAlat() {
            const keyword = searchInput.value.trim().toLowerCase();

            barisData().forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(keyword) ? '' : 'none';
            });

            perbaruiPilihan();
        }

        searchInput.addEventListener('input', cariAlat);

        selectAll.addEventListener('change', () => {
            barisTerlihat().forEach(row => {
                row.querySelector('.alat-check').checked = selectAll.checked;
            });
            perbaruiPilihan();
        });

        tableBody.addEventListener('change', event => {
            if (event.target.classList.contains('alat-check')) perbaruiPilihan();
        });

        function konfirmasiHapus() {
            const selected = barisTerpilih();
            if (!selected.length) return;

            const names = selected.map(row => row.dataset.nama);

            document.getElementById('modalText').textContent =
                'Kamu memilih ' + names.length + ' alat: ' + names.join(', ') +
                '. Data yang dihapus tidak dapat dikembalikan.';

            confirmModal.style.display = 'flex';
        }

        function tutupModal() {
            confirmModal.style.display = 'none';
        }

        function kirimHapus() {
            document.getElementById('formHapus').submit();
        }

        confirmModal.addEventListener('click', event => {
            if (event.target === confirmModal) tutupModal();
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') tutupModal();
        });

        perbaruiPilihan();
    </script>

</body>
</html>
