<?php
// ============================================================
// AUTH - PT. PARKIR BOGOR (3 ROLE)
// ============================================================
if (!isset($koneksi)) { require_once __DIR__ . '/../config/database.php'; }
if (!function_exists('flash')) { require_once __DIR__ . '/functions.php'; }

if (!function_exists('cek_login')) {
    function cek_login() {
        if (empty($_SESSION['id_user'])) {
            flash('error', 'Silakan login terlebih dahulu.');
            redirect(BASE_URL . 'login.php');
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 14400)) {
            session_unset(); session_destroy();
            redirect(BASE_URL . 'login.php?timeout=1');
        }
        $_SESSION['last_activity'] = time();
    }
}

if (!function_exists('cek_role')) {
    function cek_role($role_diminta) {
        cek_login();
        $role_sekarang = $_SESSION['role'] ?? '';
        $roles = is_array($role_diminta) ? $role_diminta : [$role_diminta];
        if (!in_array($role_sekarang, $roles)) {
            flash('error', 'Akses ditolak. Halaman ini hanya untuk: ' . implode('/', $roles) . '.');
            redirect(dashboard_url());
        }
    }
}

if (!function_exists('cek_admin')) { function cek_admin() { cek_role('admin'); } }
if (!function_exists('cek_operator')) { function cek_operator() { cek_role(['operator', 'admin']); } }
if (!function_exists('cek_pelanggan')) { function cek_pelanggan() { cek_role('pelanggan'); } }

if (!function_exists('user_id')) { function user_id() { return (int)($_SESSION['id_user'] ?? 0); } }
if (!function_exists('user_role')) { function user_role() { return $_SESSION['role'] ?? ''; } }
if (!function_exists('is_admin')) { function is_admin() { return user_role() === 'admin'; } }
if (!function_exists('is_operator')) { function is_operator() { return user_role() === 'operator'; } }
if (!function_exists('is_pelanggan')) { function is_pelanggan() { return user_role() === 'pelanggan'; } }
if (!function_exists('is_login')) { function is_login() { return !empty($_SESSION['id_user']); } }

if (!function_exists('dashboard_url')) {
    function dashboard_url() {
        switch (user_role()) {
            case 'admin': return PATH_ADMIN . 'dashboard.php';
            case 'operator': return PATH_OPERATOR . 'dashboard.php';
            case 'pelanggan': return PATH_PUBLIC . 'dashboard.php';
            default: return BASE_URL . 'login.php';
        }
    }
}
if (!function_exists('redirect_dashboard')) { function redirect_dashboard() { redirect(dashboard_url()); } }
?>