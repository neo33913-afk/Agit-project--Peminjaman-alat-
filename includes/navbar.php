<?php
/*
 * Navbar bersama. Cara pakai: tepat setelah tag body, panggil
 *     include __DIR__ . '/includes/navbar.php';
 * Menu otomatis menyesuaikan role (admin / petugas / peminjam).
 */
require_once __DIR__ . '/auth.php';

$nvUser  = auth_user($pdo);
$nvRole  = $nvUser['role'];
$nvAlias = ['edit_petugas.php' => 'daftar_user.php'];
$nvCur   = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$nvCur   = $nvAlias[$nvCur] ?? $nvCur;

$nvMenus = match ($nvRole) {
    'admin' => [
        ['Dashboard', 'dashboard_admin.php'],
        ['Alat', null, [
            ['Lihat Alat', 'lihat_alat.php'],
            ['Tambah Alat', 'tambah_alat.php'],
            ['Edit Alat', 'edit_alat.php'],
            ['Hapus Alat', 'delete_alat.php'],
        ]],
        ['Pengguna', null, [
            ['Daftar User', 'daftar_user.php'],
            ['Tambah User', 'edit_user.php?tambah=1'],
            ['Daftar Petugas', 'daftar_user.php?role=petugas'],
        ]],
        ['Peminjaman', 'status_peminjaman.php'],
        ['Laporan', 'laporan.php'],
    ],
    'petugas' => [
        ['Peminjaman', 'status_peminjaman.php'],
        ['Lihat Alat', 'lihat_alat.php'],
        ['Laporan', 'laporan.php'],
    ],
    default => [
        ['Lihat Alat', 'lihat_alat.php'],
        ['Pinjam Alat', 'pinjam_alat.php'],
        ['Status Peminjaman', 'status_peminjaman.php'],
    ],
};

function nv_is_active(string $href, string $cur): bool
{
    return basename(parse_url($href, PHP_URL_PATH)) === $cur;
}

$nvInisial = mb_strtoupper(mb_substr($nvUser['nama_lengkap'] ?: $nvUser['username'], 0, 1));
$nvPortal  = ['admin' => 'Portal Administrator', 'petugas' => 'Portal Petugas', 'peminjam' => 'Portal Peminjam'][$nvRole] ?? '';
$nvFlash   = pull_flash();
?>
<header class="nv">
    <div class="nv-inner">

        <a class="nv-brand" href="<?= e(dashboard_url($nvRole)) ?>">
            <span class="nv-logo">PA</span>
            <span class="nv-brandtext">
                <strong>Sistem Peminjaman Alat</strong>
                <small><?= e($nvPortal) ?></small>
            </span>
        </a>

        <button class="nv-toggle" type="button" id="nvToggle"
                aria-label="Buka menu" aria-expanded="false" aria-controls="nvCollapse">
            <span></span><span></span><span></span>
        </button>

        <div class="nv-collapse" id="nvCollapse">

            <nav class="nv-menu" aria-label="Menu utama">
                <?php foreach ($nvMenus as $m): ?>
                    <?php if (isset($m[2])): ?>
                        <?php
                        $groupActive = false;
                        foreach ($m[2] as $c) {
                            if (nv_is_active($c[1], $nvCur)) { $groupActive = true; }
                        }
                        ?>
                        <div class="nv-dd">
                            <button type="button"
                                    class="nv-link nv-dd-btn<?= $groupActive ? ' active' : '' ?>"
                                    aria-expanded="false">
                                <?= e($m[0]) ?> <span class="nv-caret">▾</span>
                            </button>
                            <div class="nv-dd-menu">
                                <?php foreach ($m[2] as $c): ?>
                                    <a href="<?= e($c[1]) ?>"><?= e($c[0]) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <a class="nv-link<?= nv_is_active($m[1], $nvCur) ? ' active' : '' ?>"
                           href="<?= e($m[1]) ?>"><?= e($m[0]) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <div class="nv-profile">
                <div class="nv-avatar"><?= e($nvInisial) ?></div>
                <div class="nv-info">
                    <strong><?= e($nvUser['nama_lengkap'] ?: $nvUser['username']) ?></strong>
                    <span><?= e(role_label($nvRole)) ?></span>
                </div>
                <a href="logout.php" class="nv-logout"
                   onclick="return confirm('Apakah kamu yakin ingin keluar?')">Keluar</a>
            </div>

        </div>
    </div>
</header>

<?php if ($nvFlash): ?>
    <div class="nv-flash nv-flash-<?= e($nvFlash['type']) ?>" role="status">
        <?= e($nvFlash['msg']) ?>
        <button type="button" class="nv-flash-close" aria-label="Tutup"
                onclick="this.parentNode.remove()">×</button>
    </div>
<?php endif; ?>

<script>
(function () {
    var toggle = document.getElementById('nvToggle');
    var collapse = document.getElementById('nvCollapse');

    toggle.addEventListener('click', function () {
        var open = collapse.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    function closeAll() {
        document.querySelectorAll('.nv-dd.open').forEach(function (o) {
            o.classList.remove('open');
            o.firstElementChild.setAttribute('aria-expanded', 'false');
        });
    }

    document.querySelectorAll('.nv-dd-btn').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var dd = btn.parentNode;
            var willOpen = !dd.classList.contains('open');
            closeAll();
            if (willOpen) {
                dd.classList.add('open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', closeAll);
})();
</script>
