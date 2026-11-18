<?php
// ============================================================
// API: NOTIFIKASI REAL-TIME - PT. PARKIR BOGOR
// Untuk polling jumlah kendaraan aktif
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Ambil statistik live
$res = $koneksi->query("SELECT 
    (SELECT COUNT(*) FROM transaksi_parkir WHERE waktu_keluar IS NULL AND status='aktif') AS aktif,
    (SELECT COUNT(*) FROM slot_parkir WHERE status='kosong') AS slot_kosong,
    (SELECT COUNT(*) FROM slot_parkir) AS slot_total,
    (SELECT COALESCE(SUM(total_biaya + denda), 0) FROM pembayaran WHERE DATE(waktu_bayar)=CURDATE()) AS pendapatan_hari_ini,
    (SELECT COUNT(*) FROM transaksi_parkir WHERE DATE(waktu_masuk)=CURDATE()) AS masuk_hari_ini");

$data = $res->fetch_assoc();

// Cast ke int/float
$data['aktif'] = (int)$data['aktif'];
$data['slot_kosong'] = (int)$data['slot_kosong'];
$data['slot_total'] = (int)$data['slot_total'];
$data['pendapatan_hari_ini'] = (float)$data['pendapatan_hari_ini'];
$data['masuk_hari_ini'] = (int)$data['masuk_hari_ini'];

echo json_encode([
    'success' => true,
    'timestamp' => date('c'),
    'data' => $data
]);
?>