<?php
require_once 'includes/auth.php';
cek_login();

$id = (int)($_GET['id'] ?? 0);
$tipe = $_GET['tipe'] ?? 'masuk';

$stmt = $koneksi->prepare("SELECT t.*, s.kode_slot, s.zona, u.nama_lengkap AS operator,
    p.total_biaya, p.denda, p.metode_pembayaran, p.bayar, p.kembalian, p.waktu_bayar
    FROM transaksi_parkir t
    JOIN slot_parkir s ON t.id_slot=s.id_slot
    LEFT JOIN users u ON t.operator_id=u.id_user
    LEFT JOIN pembayaran p ON p.id_transaksi=t.id_transaksi
    WHERE t.id_transaksi=? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();

if (!$trx) {
    flash('error', 'Transaksi tidak ditemukan.');
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Struk #<?= $trx['id_transaksi'] ?></title>
<link rel="stylesheet" href="assets/style.css">
<style>
  body { background:#fff; }
  .struk {
    max-width:380px; margin:20px auto; background:#fff;
    padding:24px; font-family:'Courier New',monospace;
    border:1px solid #e2e8f0; border-radius:8px;
  }
  .struk h2 { text-align:center; font-size:16px; color:#000; margin-bottom:4px; }
  .struk .center { text-align:center; font-size:12px; color:#475569; }
  .struk hr { border:none; border-top:1px dashed #94a3b8; margin:12px 0; }
  .struk table { width:100%; font-size:13px; }
  .struk table td { padding:3px 0; border:none; }
  .struk .kode { font-size:20px; font-weight:800; text-align:center; letter-spacing:2px; margin:8px 0; }
  .struk .total { font-size:18px; font-weight:800; text-align:center; padding:8px; background:#f1f5f9; border-radius:6px; }
  .struk .qr-box { text-align:center; padding:12px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; margin:12px 0; }
  .struk .qr-inner { background:#fff; display:inline-block; padding:8px; border-radius:6px; border:1px solid #e2e8f0; }
  @media print { .no-print { display:none !important; } }
</style>
</head>
<body>
<div class="struk">
  <h2><?= e($perusahaan['nama_perusahaan']) ?></h2>
  <div class="center"><?= e($perusahaan['alamat']) ?></div>
  <div class="center">Telp: <?= e($perusahaan['telepon']) ?></div>
  <hr>
  <div class="center"><b>STRUK PARKIR <?= strtoupper($tipe) ?></b></div>
  <hr>

  <div class="qr-box">
    <div class="qr-inner">
      <?= render_qr_svg($trx['kode_tiket'], 140) ?>
    </div>
    <div class="kode"><?= e($trx['kode_tiket']) ?></div>
    <div style="font-size:10px;color:#64748b;font-weight:700;">SCAN KODE QR DI POS KELUAR</div>
  </div>

  <table>
    <tr><td>No. Transaksi</td><td style="text-align:right;">#<?= (int)$trx['id_transaksi'] ?></td></tr>
    <tr><td>Kode Tiket</td><td style="text-align:right;"><?= e($trx['kode_tiket']) ?></td></tr>
    <tr><td>Plat Nomor</td><td style="text-align:right;"><b><?= e($trx['plat_nomor']) ?></b></td></tr>
    <tr><td>Jenis</td><td style="text-align:right;"><?= ucfirst($trx['jenis_kendaraan']) ?></td></tr>
    <tr><td>Slot</td><td style="text-align:right;"><?= e($trx['kode_slot']) ?> (Zona <?= e($trx['zona']) ?>)</td></tr>
    <tr><td>Masuk</td><td style="text-align:right;"><?= date('d/m/Y H:i:s', strtotime($trx['waktu_masuk'])) ?></td></tr>
    <?php if ($trx['waktu_keluar']): ?>
    <tr><td>Keluar</td><td style="text-align:right;"><?= date('d/m/Y H:i:s', strtotime($trx['waktu_keluar'])) ?></td></tr>
    <tr><td>Durasi</td><td style="text-align:right;"><?= (int)$trx['durasi_jam'] ?> jam</td></tr>
    <?php endif; ?>
  </table>

  <?php if ($trx['total_biaya']): ?>
  <hr>
  <table>
    <tr><td>Biaya Parkir</td><td style="text-align:right"><?= rupiah($trx['total_biaya']) ?></td></tr>
    <?php if ($trx['denda'] > 0): ?>
    <tr><td>Denda</td><td style="text-align:right"><?= rupiah($trx['denda']) ?></td></tr>
    <?php endif; ?>
    <tr><td><b>TOTAL</b></td><td style="text-align:right"><b><?= rupiah($trx['total_biaya'] + $trx['denda']) ?></b></td></tr>
    <tr><td>Bayar (<?= ucfirst($trx['metode_pembayaran']) ?>)</td><td style="text-align:right"><?= rupiah($trx['bayar']) ?></td></tr>
    <tr><td>Kembalian</td><td style="text-align:right"><?= rupiah($trx['kembalian']) ?></td></tr>
  </table>
  <?php endif; ?>

  <hr>
  <div class="center" style="font-size:11px;">
    <?= date('d/m/Y H:i:s') ?><br>
    Operator: <?= e($trx['operator'] ?? 'Kiosk Mandiri') ?><br>
    <br>
    *** TERIMA KASIH ***<br>
    Simpan struk ini sebagai bukti
  </div>
</div>

<div class="no-print text-center" style="margin:20px auto;max-width:380px;">
  <button class="btn btn-primary" onclick="window.print()">🖨️ Cetak</button>
  <a href="dashboard.php" class="btn btn-secondary">← Kembali</a>
</div>
</body>
</html>
