<?php
// ============================================================
// STRUK PEMBAYARAN - PELANGGAN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_pelanggan();

$uid = user_id();
$id = (int)($_GET['id'] ?? 0);

$stmt = $koneksi->prepare("SELECT t.*, s.kode_slot, s.zona,
    p.total_biaya, p.denda, p.metode_pembayaran, p.bayar, p.kembalian, p.waktu_bayar
    FROM transaksi_parkir t
    JOIN slot_parkir s ON t.id_slot = s.id_slot
    LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
    WHERE t.id_transaksi = ? AND t.user_id = ? LIMIT 1");
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();

if (!$trx) {
    flash('error', 'Transaksi tidak ditemukan.');
    redirect(PATH_PUBLIC . 'riwayat.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Struk #<?= $trx['id_transaksi'] ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css">
<style>
body { background: #f1f5f9; padding: 20px; }
.struk {
  max-width: 400px; margin: 20px auto; background: #fff;
  padding: 28px; font-family: 'Courier New', monospace;
  border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.1);
}
.struk h2 { text-align: center; font-size: 16px; margin-bottom: 4px; color: #000; }
.struk .center { text-align: center; font-size: 12px; color: #475569; }
.struk hr { border: none; border-top: 1px dashed #94a3b8; margin: 14px 0; }
.struk table { width: 100%; font-size: 13px; }
.struk table td { padding: 4px 0; border: none; }
.struk .total { font-size: 20px; font-weight: 800; text-align: center; padding: 12px; background: #f1f5f9; border-radius: 8px; margin-top: 10px; }
.struk .qr-box { text-align:center; padding:12px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; margin:12px 0; }
.struk .qr-inner { background:#fff; display:inline-block; padding:8px; border-radius:6px; border:1px solid #e2e8f0; }
@media print {
  body { background: #fff; padding: 0; }
  .no-print { display: none !important; }
  .struk { box-shadow: none; margin: 0; }
}
</style>
</head>
<body>
<div class="struk">
  <h2><?= e($perusahaan['nama_perusahaan']) ?></h2>
  <div class="center"><?= e($perusahaan['alamat']) ?></div>
  <div class="center">Telp: <?= e($perusahaan['telepon']) ?></div>
  <hr>
  <div class="center"><b>STRUK PEMBAYARAN</b></div>
  <hr>

  <div class="qr-box">
    <div class="qr-inner">
      <?= render_qr_svg($trx['kode_tiket'], 140) ?>
    </div>
    <div style="font-size:18px;font-weight:800;text-align:center;letter-spacing:2px;margin:8px 0;color:#1e3a8a;"><?= e($trx['kode_tiket']) ?></div>
    <div style="font-size:10px;color:#64748b;font-weight:700;">SCAN KODE QR DI POS KELUAR</div>
  </div>

  <table>
    <tr><td>No. Transaksi</td><td align="right">#<?= (int)$trx['id_transaksi'] ?></td></tr>
    <tr><td>Kode Tiket</td><td align="right"><?= e($trx['kode_tiket']) ?></td></tr>
    <tr><td>Plat Nomor</td><td align="right"><b><?= e($trx['plat_nomor']) ?></b></td></tr>
    <tr><td>Jenis</td><td align="right"><?= ucfirst($trx['jenis_kendaraan']) ?></td></tr>
    <tr><td>Slot</td><td align="right"><?= e($trx['kode_slot']) ?></td></tr>
    <tr><td>Masuk</td><td align="right"><?= date('d/m/Y H:i', strtotime($trx['waktu_masuk'])) ?></td></tr>
    <tr><td>Keluar</td><td align="right"><?= $trx['waktu_keluar'] ? date('d/m/Y H:i', strtotime($trx['waktu_keluar'])) : '-' ?></td></tr>
    <tr><td>Durasi</td><td align="right"><?= (int)$trx['durasi_jam'] ?> jam</td></tr>
  </table>
  <hr>
  <table>
    <tr><td>Biaya Parkir</td><td align="right"><?= rupiah($trx['total_biaya']) ?></td></tr>
    <?php if ($trx['denda'] > 0): ?>
    <tr><td>Denda</td><td align="right"><?= rupiah($trx['denda']) ?></td></tr>
    <?php endif; ?>
    <tr><td><b>TOTAL</b></td><td align="right"><b><?= rupiah($trx['total_biaya'] + $trx['denda']) ?></b></td></tr>
    <tr><td>Metode</td><td align="right"><?= ucfirst($trx['metode_pembayaran']) ?></td></tr>
  </table>
  <div class="total"><?= rupiah($trx['total_biaya'] + $trx['denda']) ?></div>
  <hr>
  <div class="center" style="font-size:11px;">
    <?= $trx['waktu_bayar'] ? date('d/m/Y H:i:s', strtotime($trx['waktu_bayar'])) : date('d/m/Y H:i:s') ?><br>
    *** TERIMA KASIH ***<br>
    Simpan struk ini sebagai bukti
  </div>
</div>

<div class="no-print text-center" style="margin:20px auto;max-width:400px;">
  <button class="btn btn-primary" onclick="window.print()">🖨️ Cetak</button>
  <a href="<?= PATH_PUBLIC ?>riwayat.php" class="btn btn-secondary">← Riwayat</a>
  <a href="<?= PATH_PUBLIC ?>dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
</div>
</body>
</html>
