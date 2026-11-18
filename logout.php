<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['id_user'])) {
    log_aktivitas($koneksi, $_SESSION['id_user'], 'logout', 'Logout dari sistem');
}
session_unset();
session_destroy();
header('Location: ' . BASE_URL . 'login.php');
exit;
?>