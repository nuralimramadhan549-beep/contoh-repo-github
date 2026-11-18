<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$slots = [];
$q = $koneksi->query("SELECT id_slot, kode_slot, jenis_kendaraan, zona FROM slot_parkir WHERE status='kosong' ORDER BY jenis_kendaraan, kode_slot");
while ($r = $q->fetch_assoc()) $slots[] = $r;

$msg = flash('kiosk_msg');
$tiket = $_GET['tiket'] ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kiosk Mandiri — <?= e($perusahaan['nama_perusahaan']) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="kiosk-wrapper">
  <div class="kiosk-box">
    <div style="text-align:center;font-size:60px;">🅿️</div>
    <h1><?= e($perusahaan['nama_perusahaan']) ?></h1>
    <p class="sub">Kiosk Tiket Mandiri · <?= e($perusahaan['jam_operasional']) ?></p>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

    <?php if ($tiket): ?>
      <div class="tiket">
        <div style="font-size:13px;color:#64748b;">KODE TIKET ANDA</div>
        <div class="kode"><?= e($tiket) ?></div>
        <div style="border-top:2px dashed #cbd5e1;margin:16px 0;"></div>
        <div style="font-size:13px;color:#64748b;">SIMPAN / SCREENSHOT KODE INI</div>
        <p style="margin-top:16px;font-size:13px;">Gunakan kode ini saat keluar. Jika hilang, dikenakan denda.</p>
        <a href="kiosk.php" class="btn btn-primary btn-lg" style="margin-top:20px;">🎫 Ambil Tiket Baru</a>
      </div>
    <?php else: ?>
      <form action="kiosk_proses.php" method="post" autocomplete="off">
        <div class="form-group">
          <label style="font-size:18px;">🚘 Plat Nomor Kendaraan</label>
          <input type="text" name="plat_nomor" required placeholder="B 1234 XYZ"
                 style="text-transform:uppercase;font-weight:800;letter-spacing:3px;text-align:center;">
        </div>
        <div class="form-group">
          <label style="font-size:18px;">🚙 Jenis Kendaraan</label>
          <select name="jenis_kendaraan" id="jenis" required onchange="filterSlot(this.value,'slot')">
            <option value="">-- Pilih Jenis --</option>
            <option value="motor">🏍️ Motor (<?= slot_tersedia($koneksi,'motor') ?> slot)</option>
            <option value="mobil">🚗 Mobil (<?= slot_tersedia($koneksi,'mobil') ?> slot)</option>
          </select>
        </div>
        <div class="form-group">
          <label style="font-size:18px;">🅿️ Pilih Slot</label>
          <select name="id_slot" id="slot" required>
            <option value="">-- Pilih jenis dulu --</option>
          </select>
        </div>
        <button type="submit" class="btn btn-success">🎫 Ambil Tiket Sekarang</button>
      </form>

      <div style="margin-top:30px;padding-top:20px;border-top:1px solid #e2e8f0;text-align:center;">
        <p style="font-size:13px;color:#64748b;">
          Butuh bantuan? Hubungi petugas di pos parkir.
        </p>
        <p style="font-size:13px;color:#64748b;margin-top:6px;">
          <a href="index.php" style="color:#1e3a8a;">Login Petugas →</a>
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>window.__slots = <?= json_encode($slots) ?>;</script>
<script src="assets/app.js"></script>
</body>
</html>