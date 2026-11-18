<?php
// ============================================================
// CRON CLEANUP - PT. PARKIR BOGOR
// Batalkan transaksi menggantung > 7 hari
// Akses: /cron_cleanup.php?key=parkirbogor
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$key = $_GET['key'] ?? '';
$allowed = ['parkirbogor', 'cron_secret_2026'];
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && !in_array($key, $allowed)) {
    die('Access denied. Add ?key=parkirbogor');
}

$q = $koneksi->query("SELECT t.id_transaksi, t.id_slot, t.plat_nomor 
    FROM transaksi_parkir t
    LEFT JOIN pembayaran p ON p.id_transaksi=t.id_transaksi
    WHERE t.waktu_keluar IS NULL 
    AND t.status='aktif'
    AND t.waktu_masuk < DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND p.id_pembayaran IS NULL");

$n = 0;
while ($r = $q->fetch_assoc()) {
    $koneksi->query("UPDATE transaksi_parkir SET status='batal', catatan='Auto-batal > 7 hari' WHERE id_transaksi={$r['id_transaksi']}");
    $koneksi->query("UPDATE slot_parkir SET status='kosong' WHERE id_slot={$r['id_slot']}");
    $n++;
}

$msg = "[OK] $n transaksi lama dibatalkan";
echo $is_cli ? "$msg\n" : $msg;
?>