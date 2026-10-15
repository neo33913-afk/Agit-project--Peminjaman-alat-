<?php
/*
 * Helper bersama: sesi, hak akses (role), CSRF, flash message, format.
 * Dipanggil di paling atas setiap halaman: require_once __DIR__.'/includes/auth.php';
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/koneksi.php';

const DENDA_PER_HARI = 5000;     // denda keterlambatan per hari (Rp)
const LOGIN_PAGE     = 'login.html';

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------- Login & role ---------- */

function auth_user(PDO $pdo): array
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        redirect(LOGIN_PAGE);
    }
    $s = $pdo->prepare('SELECT id, username, nama_lengkap, role FROM users WHERE id = ?');
    $s->execute([$_SESSION['user_id']]);
    $user = $s->fetch();
    if (!$user) {
        session_destroy();
        redirect(LOGIN_PAGE);
    }
    return $user;
}

function dashboard_url(string $role): string
{
    return match ($role) {
        'admin'   => 'dashboard_admin.php',
        'petugas' => 'status_peminjaman.php',
        default   => 'lihat_alat.php',
    };
}

/** Batasi halaman hanya untuk role tertentu. */
function require_role(PDO $pdo, array $roles): array
{
    $u = auth_user($pdo);
    if (!in_array($u['role'], $roles, true)) {
        set_flash('error', 'Kamu tidak punya akses ke halaman itu.');
        redirect(dashboard_url($u['role']));
    }
    return $u;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Silakan muat ulang halaman.');
    }
}

/* ---------- Flash message ---------- */

function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function pull_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Logika peminjaman ---------- */

/** Peminjaman yang lewat jatuh tempo otomatis berstatus "terlambat". */
function sinkron_terlambat(PDO $pdo): void
{
    $pdo->exec("UPDATE peminjaman SET status = 'terlambat'
                WHERE status = 'dipinjam' AND tanggal_jatuh_tempo < CURDATE()");
}

function hari_terlambat(string $jatuhTempo, ?string $tglKembali = null): int
{
    $akhir = strtotime($tglKembali ?: date('Y-m-d'));
    $batas = strtotime($jatuhTempo);
    return max(0, (int)floor(($akhir - $batas) / 86400));
}

/* ---------- Format ---------- */

function tgl_id(?string $d): string
{
    if (!$d || $d === '0000-00-00') {
        return '-';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($d);
    return date('j', $t) . ' ' . $bulan[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function kode(string $prefix, int $id): string
{
    return $prefix . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
}

function role_label(string $role): string
{
    return ['admin' => 'Admin', 'petugas' => 'Petugas', 'peminjam' => 'Peminjam'][$role] ?? ucfirst($role);
}

function status_label(string $s): string
{
    return [
        'diajukan'     => 'Menunggu',
        'dipinjam'     => 'Sedang Dipinjam',
        'terlambat'    => 'Terlambat',
        'dikembalikan' => 'Dikembalikan',
        'ditolak'      => 'Ditolak',
    ][$s] ?? ucfirst($s);
}
