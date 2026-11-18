<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('kiosk.php');

$plat = validasi_plat($_POST['plat_nomor'] ?? '');
$jenis = $_POST['jenis_kendaraan'] ?? '';
$id_slot = (int)($_POST['id_slot'] ?? 0);

if (!$plat) { flash('kiosk_msg', '❌ Format plat tidak valid.'); redirect('kiosk.php'); }
if (!in_array($jenis, ['motor','mobil'])) { flash('kiosk_msg', '❌ Pilih jenis kendaraan.'); redirect('kiosk.php'); }
if ($id_slot <= 0) { flash('kiosk_msg', '❌ Pilih slot parkir.'); redirect('kiosk.php'); }

if (plat_sedang_parkir($koneksi, $plat)) {
    flash('kiosk_msg', "❌ Kendaraan $plat masih terparkir.");
    redirect('kiosk.php');
}

$stmt = $koneksi->prepare("SELECT * FROM slot_parkir WHERE id_slot=? AND status='kosong' AND jenis_kendaraan=? LIMIT 1");
$stmt->bind_param('is', $id_slot, $jenis);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    flash('kiosk_msg', '❌ Slot tidak tersedia.');
    redirect('kiosk.php');
}

$koneksi->begin_transaction();
try {
    $kode = generate_kode_tiket($koneksi);
    $stmt = $koneksi->prepare("INSERT INTO transaksi_parkir 
        (id_slot, jenis_kendaraan, plat_nomor, kode_tiket, via_kiosk) 
        VALUES (?,?,?,?,1)");
    $stmt->bind_param('isss', $id_slot, $jenis, $plat, $kode);
    $stmt->execute();

    $koneksi->query("UPDATE slot_parkir SET status='terisi' WHERE id_slot=$id_slot");
    $koneksi->commit();

    redirect('kiosk.php?tiket=' . urlencode($kode));
} catch (Exception $e) {
    $koneksi->rollback();
    flash('kiosk_msg', '❌ Gagal membuat tiket.');
    redirect('kiosk.php');
}
?>