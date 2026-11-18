<?php
// ============================================================
// PROSES KIOSK PARKIR OTOMATIS - PT. PARKIR BOGOR
// Smart Automated Allocation: Auto-assign Slot & Generate Ticket
// ============================================================
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('kiosk.php');

$plat = validasi_plat($_POST['plat_nomor'] ?? '');
$jenis = $_POST['jenis_kendaraan'] ?? 'mobil';
$id_slot = (int)($_POST['id_slot'] ?? 0);

if (!$plat) { 
    flash('kiosk_msg', '❌ Format plat nomor tidak valid.'); 
    redirect('kiosk.php'); 
}
if (!in_array($jenis, ['motor','mobil'])) { 
    flash('kiosk_msg', '❌ Pilih jenis kendaraan yang valid.'); 
    redirect('kiosk.php'); 
}

// Alokasi slot otomatis jika belum dipilih (Fitur Parkir Otomatis)
if ($id_slot <= 0) {
    try {
        $stmtSlot = $koneksi->prepare("SELECT id_slot FROM slot_parkir WHERE jenis_kendaraan=? AND status='kosong' ORDER BY zona ASC, kode_slot ASC LIMIT 1");
        if ($stmtSlot) {
            $stmtSlot->bind_param('s', $jenis);
            $stmtSlot->execute();
            $rSlot = $stmtSlot->get_result()->fetch_assoc();
            $stmtSlot->close();
            if ($rSlot) {
                $id_slot = (int)$rSlot['id_slot'];
            }
        }
    } catch (Exception $e) {}
}

if ($id_slot <= 0) { 
    flash('kiosk_msg', "❌ Maaf, seluruh slot bay untuk " . ($jenis === 'motor' ? 'Sepeda Motor' : 'Mobil') . " saat ini sedang penuh!"); 
    redirect('kiosk.php'); 
}

if (plat_sedang_parkir($koneksi, $plat)) {
    flash('kiosk_msg', "❌ Kendaraan $plat sudah tercatat sedang aktif parkir.");
    redirect('kiosk.php');
}

$stmt = $koneksi->prepare("SELECT * FROM slot_parkir WHERE id_slot=? AND status='kosong' AND jenis_kendaraan=? LIMIT 1");
$stmt->bind_param('is', $id_slot, $jenis);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    flash('kiosk_msg', '❌ Slot yang dialokasikan tidak tersedia, silakan coba lagi.');
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

    redirect('kiosk.php?tiket=' . urlencode($kode) . '&auto=1');
} catch (Exception $e) {
    $koneksi->rollback();
    flash('kiosk_msg', '❌ Gagal memproses tiket otomatis: ' . $e->getMessage());
    redirect('kiosk.php');
}
