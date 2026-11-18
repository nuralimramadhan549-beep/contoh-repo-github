<?php
// ============================================================
// TERMINAL PARKIR OTOMATIS & BARRIER GATE - PT. PARKIR BOGOR
// Standard: Smart Automated Entrance Gate Console
// Theme: Obsidian Dark Glassmorphism (Identik dengan login.php)
// Features: ANPR Scanner, Auto Slot Allocation & Auto Barrier Gate
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$slots = [];
try {
    $q = $koneksi->query("SELECT id_slot, kode_slot, jenis_kendaraan, zona FROM slot_parkir WHERE status='kosong' ORDER BY zona ASC, kode_slot ASC");
    if ($q) {
        while ($r = $q->fetch_assoc()) $slots[] = $r;
    }
} catch (Exception $e) {}

$msg = flash('kiosk_msg');
$tiket = $_GET['tiket'] ?? null;
$is_auto = isset($_GET['auto']);

$trx = null;
if ($tiket) {
    try {
        $stmt = $koneksi->prepare("
            SELECT t.*, s.kode_slot, s.zona 
            FROM transaksi_parkir t 
            JOIN slot_parkir s ON t.id_slot = s.id_slot 
            WHERE t.kode_tiket = ? 
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('s', $tiket);
            $stmt->execute();
            $trx = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    } catch (Exception $e) {}
}

$slot_mobil_count = slot_tersedia($koneksi, 'mobil');
$slot_motor_count = slot_tersedia($koneksi, 'motor');

// Cari slot rekomendasi otomatis pertama
$slot_mobil_rek = 'A-01 (Zona A)';
$slot_motor_rek = 'C-01 (Zona C)';
foreach ($slots as $s) {
    if ($s['jenis_kendaraan'] === 'mobil' && $slot_mobil_rek === 'A-01 (Zona A)') {
        $slot_mobil_rek = $s['kode_slot'] . ' (Zona ' . ($s['zona'] ?? 'A') . ')';
    }
    if ($s['jenis_kendaraan'] === 'motor' && $slot_motor_rek === 'C-01 (Zona C)') {
        $slot_motor_rek = $s['kode_slot'] . ' (Zona ' . ($s['zona'] ?? 'C') . ')';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terminal Parkir Otomatis — <?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css?v=<?= time() ?>">
<style>
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; padding: 0; min-height: 100%; }
body {
  background: #090d16; min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 24px; position: relative; overflow-x: hidden;
  font-family: 'Inter', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  color: #e2e8f0;
}
body::before, body::after {
  content: ''; position: fixed; border-radius: 50%; pointer-events: none; z-index: 0;
}
body::before {
  top: -150px; left: -150px; width: 550px; height: 550px;
  background: radial-gradient(circle, rgba(37,99,235,.25) 0%, transparent 70%);
}
body::after {
  bottom: -150px; right: -150px; width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(6,182,212,.18) 0%, transparent 70%);
}

.kiosk-card {
  width: 100%; max-width: 500px; background: #0f172a;
  border: 1px solid rgba(255,255,255,.12); border-radius: 24px;
  padding: 38px 34px;
  box-shadow: 0 30px 70px rgba(0,0,0,.6), 0 0 30px rgba(37,99,235,.15);
  position: relative; z-index: 10;
}

.kiosk-brand {
  display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 20px;
}
.kiosk-brand-icon {
  width: 58px; height: 58px; border-radius: 16px;
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; color: #fff; font-weight: 900;
  box-shadow: 0 8px 24px rgba(37,99,235,.4);
  margin-bottom: 12px; border: 1px solid rgba(255,255,255,.2);
}
.kiosk-brand h1 {
  font-family: 'Plus Jakarta Sans','Inter',sans-serif;
  font-size: 22px; font-weight: 800; color: #fff;
  letter-spacing: -.5px; margin: 0 0 4px; line-height: 1.25;
}
.kiosk-brand p { font-size: 13px; color: #94a3b8; margin: 0; }

/* Status Bar Terminal Otomatis */
.kiosk-status-strip {
  display: flex; justify-content: center; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;
}
.kiosk-pill {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 5px 12px; border-radius: 9999px; font-size: 11.5px; font-weight: 700;
  background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
}
.kiosk-pill.gate { color: #34d399; border-color: rgba(16,185,129,0.3); background: rgba(16,185,129,0.1); }
.kiosk-pill.car { color: #38bdf8; border-color: rgba(56,189,248,0.3); background: rgba(56,189,248,0.08); }
.kiosk-pill.bike { color: #a78bfa; border-color: rgba(167,139,250,0.3); background: rgba(167,139,250,0.08); }

.radar-dot {
  width: 7px; height: 7px; border-radius: 50%;
  animation: pulseDot 1.8s infinite;
}
.radar-dot.cyan { background: #38bdf8; box-shadow: 0 0 0 3px rgba(56,189,248,0.3); }
.radar-dot.green { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.3); }
@keyframes pulseDot {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.3); opacity: 0.6; }
}

/* ANPR CAMERA SCANNER FEED */
.anpr-camera-box {
  background: #090d16;
  border: 1px solid rgba(56, 189, 248, 0.3);
  border-radius: 14px;
  padding: 14px 16px;
  margin-bottom: 20px;
  position: relative;
  overflow: hidden;
}
.anpr-camera-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  font-weight: 800;
  color: #38bdf8;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  margin-bottom: 10px;
}
.anpr-live-tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.35);
  color: #f87171;
  padding: 2px 8px;
  border-radius: 9999px;
  font-size: 10px;
  font-weight: 800;
}
.anpr-scan-line {
  position: absolute;
  left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, #38bdf8, #10b981, transparent);
  box-shadow: 0 0 8px #38bdf8;
  animation: anprScan 2.5s infinite ease-in-out;
  pointer-events: none;
}
@keyframes anprScan {
  0%   { top: 10%; opacity: 0; }
  20%  { opacity: 1; }
  80%  { opacity: 1; }
  100% { top: 90%; opacity: 0; }
}
.anpr-detect-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}
.anpr-plate-display {
  font-family: 'JetBrains Mono', monospace;
  font-size: 20px;
  font-weight: 800;
  color: #ffffff;
  background: #111827;
  border: 1px solid #334155;
  padding: 6px 14px;
  border-radius: 8px;
  letter-spacing: 2px;
  text-transform: uppercase;
}
.btn-anpr-reroll {
  background: rgba(56, 189, 248, 0.1);
  border: 1px solid rgba(56, 189, 248, 0.3);
  color: #38bdf8;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 11.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.btn-anpr-reroll:hover {
  background: #38bdf8; color: #090d16;
}

/* Touch Vehicle Selector Cards */
.vehicle-type-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px;
}
.vehicle-btn {
  background: #0b1220; border: 1.5px solid #1e293b; border-radius: 12px;
  padding: 12px 10px; text-align: center; cursor: pointer;
  transition: all 0.2s ease; display: flex; flex-direction: column; align-items: center; gap: 4px;
  color: #cbd5e1; user-select: none;
}
.vehicle-btn:hover { border-color: #334155; background: #0f1a30; }
.vehicle-btn.active {
  background: linear-gradient(135deg, rgba(37,99,235,0.2) 0%, rgba(79,70,229,0.25) 100%);
  border-color: #3b82f6; color: #ffffff;
  box-shadow: 0 0 16px rgba(37,99,235,0.3);
}
.vehicle-btn .v-icon { font-size: 24px; }
.vehicle-btn .v-label { font-size: 13.5px; font-weight: 700; }
.vehicle-btn .v-slot { font-size: 11px; color: #94a3b8; }
.vehicle-btn.active .v-slot { color: #38bdf8; font-weight: 600; }

/* Auto Allocated Slot Preview Pill */
.auto-slot-card {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.3);
  border-radius: 12px;
  padding: 12px 14px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12.5px;
}
.auto-slot-card b {
  color: #34d399;
  font-family: 'JetBrains Mono', monospace;
  font-size: 13.5px;
}

/* Plate Form Group */
.form-group-kiosk {
  display: flex; flex-direction: column; margin-bottom: 16px; width: 100%;
}
.form-group-kiosk label {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 6px; font-size: 11.5px;
  font-weight: 700; color: #cbd5e1; letter-spacing: .02em;
  text-transform: uppercase;
}
.plate-input-kiosk {
  display: block; width: 100%; box-sizing: border-box; height: 50px;
  padding: 0 16px; background: #0b1220; border: 1.5px solid #1e293b;
  color: #38bdf8; border-radius: 12px; font-size: 20px; font-family: 'JetBrains Mono', monospace;
  font-weight: 800; letter-spacing: 3px; text-align: center; text-transform: uppercase;
  outline: none; transition: all 0.15s ease;
}
.plate-input-kiosk:hover { border-color: #334155; }
.plate-input-kiosk:focus {
  border-color: #2563eb; background: #020617; color: #60a5fa;
  box-shadow: 0 0 0 3px rgba(37,99,235,.25);
}

/* Big Automatic Ticket Button */
.btn-auto-submit {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; height: 54px; margin-top: 14px; padding: 0 20px;
  background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
  color: #ffffff; border: none; border-radius: 14px;
  font-family: inherit; font-size: 15.5px; font-weight: 800;
  letter-spacing: .02em; cursor: pointer;
  transition: transform .1s, box-shadow .15s, filter .15s;
  box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
}
.btn-auto-submit:hover { filter: brightness(1.1); box-shadow: 0 10px 28px rgba(16, 185, 129, 0.55); }
.btn-auto-submit:active { transform: translateY(1px); }

/* BARRIER GATE SIMULATION (TAMPILAN GERBANG TERBUKA) */
.gate-simulation-box {
  background: #090d16;
  border: 1px solid rgba(16, 185, 129, 0.35);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 20px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.gate-status-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  background: rgba(16, 185, 129, 0.15);
  border: 1px solid rgba(16, 185, 129, 0.4);
  border-radius: 9999px;
  color: #34d399;
  font-size: 13px;
  font-weight: 800;
  margin-bottom: 16px;
  animation: pulseDot 1.5s infinite;
}
.gate-arm-graphic {
  width: 100%; height: 90px;
  display: flex; align-items: flex-end; justify-content: center;
  position: relative; margin-bottom: 12px;
}
.gate-pillar {
  width: 24px; height: 65px;
  background: linear-gradient(180deg, #f59e0b, #d97706);
  border-radius: 4px; border: 1px solid #78350f;
  position: relative;
}
.gate-traffic-light {
  position: absolute; top: 8px; left: 5px; width: 14px; height: 14px;
  background: #10b981; border-radius: 50%;
  box-shadow: 0 0 12px #34d399;
}
.gate-arm-bar {
  width: 160px; height: 10px;
  background: repeating-linear-gradient(45deg, #ef4444, #ef4444 14px, #ffffff 14px, #ffffff 28px);
  border-radius: 4px;
  transform-origin: left bottom;
  transform: rotate(-75deg);
  transition: transform 1s ease-out;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
  margin-left: 2px;
}
.gate-instruction {
  font-size: 14px; font-weight: 700; color: #ffffff; margin: 0 0 4px 0;
}
.gate-countdown {
  font-size: 11.5px; color: #94a3b8;
}

/* Struk Tiket Fisik */
.ticket-receipt-card {
  width: 100%; background: #ffffff; color: #0f172a;
  border-radius: 14px; padding: 22px 20px;
  box-shadow: 0 15px 35px rgba(0,0,0,0.5);
  font-family: 'JetBrains Mono', monospace;
  margin-bottom: 18px; text-align: center;
}
.ticket-cut-top {
  border-bottom: 2px dashed #cbd5e1; padding-bottom: 10px; margin-bottom: 12px;
}
.ticket-cut-top h3 {
  font-size: 15px; font-weight: 800; color: #0f172a; margin: 0 0 2px 0;
}
.ticket-cut-top p {
  font-size: 10.5px; color: #64748b; margin: 0;
}
.ticket-qr-wrap {
  display: flex; justify-content: center; align-items: center;
  padding: 10px; background: #f8fafc; border-radius: 10px;
  border: 1px solid #e2e8f0; margin-bottom: 12px;
}
.ticket-qr-wrap svg { display: block; max-width: 135px; height: auto; }
.ticket-big-code {
  font-size: 19px; font-weight: 800; color: #0284c7;
  letter-spacing: 1px; margin-bottom: 10px;
}
.ticket-meta-table {
  width: 100%; font-size: 11.5px; border-top: 1px dashed #cbd5e1;
  padding-top: 10px; margin-bottom: 12px;
}
.ticket-meta-table .row {
  display: flex; justify-content: space-between; margin-bottom: 4px;
}
.ticket-meta-table .label { color: #64748b; }
.ticket-meta-table .val { font-weight: 700; color: #0f172a; }

.ticket-actions-row {
  display: flex; gap: 10px; width: 100%;
}
.btn-ticket-print {
  flex: 1; height: 46px; border-radius: 10px; border: none;
  background: #1e293b; color: #ffffff; font-weight: 700; font-size: 13px;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
  transition: all 0.2s;
}
.btn-ticket-print:hover { background: #334155; }
.btn-ticket-new {
  flex: 1.3; height: 46px; border-radius: 10px; border: none;
  background: linear-gradient(135deg, #2563eb, #4f46e5); color: #ffffff;
  font-weight: 700; font-size: 13px; text-decoration: none;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
  box-shadow: 0 4px 14px rgba(37,99,235,0.4);
}
.btn-ticket-new:hover { filter: brightness(1.08); }

/* Alerts */
.kiosk-card .alert {
  padding: 12px 14px; border-radius: 10px; margin-bottom: 18px;
  font-size: 13px; line-height: 1.4; border: 1px solid transparent; text-align: center;
}
.kiosk-card .alert-success { background: rgba(22,101,52,.18); color: #4ade80; border-color: rgba(74,222,128,.3); }
.kiosk-card .alert-danger  { background: rgba(153,27,27,.18); color: #f87171; border-color: rgba(248,113,113,.3); }

/* Footer */
.kiosk-card .auth-footer {
  text-align: center; margin-top: 24px; padding-top: 18px;
  border-top: 1px solid rgba(255,255,255,.08); font-size: 12.5px; color: #94a3b8;
}
.kiosk-card .auth-footer p { margin: 0 0 10px; }
.kiosk-card .auth-footer .auth-links {
  display: flex; justify-content: center; align-items: center;
  gap: 10px; flex-wrap: wrap; font-size: 12px; color: #64748b;
}
.kiosk-card .auth-footer .auth-links a { color: #94a3b8; text-decoration: none; transition: color .15s; }
.kiosk-card .auth-footer .auth-links a:hover { color: #38bdf8; }

@media (max-width: 480px) {
  body { padding: 14px; }
  .kiosk-card { padding: 28px 18px; border-radius: 18px; }
  .kiosk-brand h1 { font-size: 18px; }
}

@media print {
  body { background: #fff !important; padding: 0 !important; }
  body::before, body::after, .kiosk-brand, .kiosk-status-strip, .gate-simulation-box, .ticket-actions-row, .auth-footer { display: none !important; }
  .kiosk-card {
    border: none !important; box-shadow: none !important; background: #fff !important;
    padding: 0 !important; max-width: 100% !important;
  }
  .ticket-receipt-card { box-shadow: none !important; padding: 10px !important; }
}
</style>
</head>
<body>

<div class="kiosk-card">
  <!-- BRAND HEADER -->
  <div class="kiosk-brand">
    <div class="kiosk-brand-icon" aria-hidden="true">🤖</div>
    <h1><?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?></h1>
    <p>Terminal Parkir Otomatis & Barrier Gate 01</p>
  </div>

  <!-- STATUS TELEMETRY -->
  <div class="kiosk-status-strip">
    <div class="kiosk-pill gate">
      <span class="radar-dot green"></span>
      <span>Loop Sensor: <b>Aktif</b></span>
    </div>
    <div class="kiosk-pill car">
      <span class="radar-dot cyan"></span>
      <span>🚗 Mobil: <b><?= (int)$slot_mobil_count ?></b></span>
    </div>
    <div class="kiosk-pill bike">
      <span class="radar-dot green"></span>
      <span>🏍️ Motor: <b><?= (int)$slot_motor_count ?></b></span>
    </div>
  </div>

  <?php if ($msg): ?>
    <div class="alert <?= strpos($msg, '❌') !== false ? 'alert-danger' : 'alert-success' ?>" role="alert">
      <?= e($msg) ?>
    </div>
  <?php endif; ?>

  <?php if ($tiket): ?>
    <!-- ============================================================
         TAMPILAN PARKIR OTOMATIS: PALANG TERBUKA & STRUK RESMI
         ============================================================ -->
    <!-- 1. SIMULASI GERBANG OTOMATIS TERBUKA -->
    <div class="gate-simulation-box">
      <div class="gate-status-badge">
        <span class="radar-dot green"></span>
        <span>PALANG PINTU TERBUKA (BARRIER OPEN)</span>
      </div>

      <div class="gate-arm-graphic">
        <div class="gate-pillar">
          <div class="gate-traffic-light"></div>
        </div>
        <div class="gate-arm-bar"></div>
      </div>

      <div class="gate-instruction">
        🟢 Silakan Masuk, Palang Gerbang Terbuka!
      </div>
      <div class="gate-countdown">
        Palang otomatis menutup setelah kendaraan lewat · <span id="timerReset" style="color:#38bdf8;font-weight:700;">10s</span>
      </div>
    </div>

    <!-- 2. STRUK TIKET FISIK DISPENSER OTOMATIS -->
    <div class="ticket-receipt-card" id="printableTicket">
      <div class="ticket-cut-top">
        <h3><?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?></h3>
        <p><?= e($perusahaan['alamat'] ?? 'Jl. Pajajaran No. 1, Kota Bogor') ?></p>
        <p>Jam: <?= e($perusahaan['jam_operasional'] ?? '24 Jam') ?> · Telp: <?= e($perusahaan['telepon'] ?? '0251-123456') ?></p>
      </div>

      <div style="font-size:11px;color:#64748b;margin-bottom:4px;font-weight:700;">
        TIKET MASUK RESMI SISTEM OTOMATIS
      </div>

      <div class="ticket-big-code">
        🎫 <?= e($tiket) ?>
      </div>

      <div class="ticket-qr-wrap">
        <?php
          $qrSvg = render_qr_svg($tiket, 135);
          if (!empty($qrSvg)) {
              echo $qrSvg;
          } else {
              echo '<div style="padding:15px;font-size:11px;color:#64748b;">[ QR CODE SCANNER ACTIVE ]</div>';
          }
        ?>
      </div>

      <div class="ticket-meta-table">
        <?php if ($trx): ?>
          <div class="row">
            <span class="label">Plat Terdeteksi:</span>
            <span class="val" style="color:#0284c7;"><?= e($trx['plat_nomor']) ?></span>
          </div>
          <div class="row">
            <span class="label">Jenis:</span>
            <span class="val"><?= $trx['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?></span>
          </div>
          <div class="row">
            <span class="label">Alokasi Bay:</span>
            <span class="val" style="color:#10b981;font-weight:800;"><?= e($trx['kode_slot']) ?> (Zona <?= e($trx['zona'] ?? 'A') ?>)</span>
          </div>
          <div class="row">
            <span class="label">Waktu Masuk:</span>
            <span class="val"><?= date('d/m/Y H:i:s', strtotime($trx['waktu_masuk'])) ?></span>
          </div>
        <?php else: ?>
          <div class="row">
            <span class="label">Waktu Cetak:</span>
            <span class="val"><?= date('d/m/Y H:i:s') ?></span>
          </div>
        <?php endif; ?>
        <div class="row">
          <span class="label">Gerbang:</span>
          <span class="val">Auto-Gate 01 In</span>
        </div>
      </div>

      <div style="font-size:10px;color:#64748b;border-top:1px dashed #cbd5e1;padding-top:8px;">
        ⚠️ Harap simpan tiket QR ini. Gunakan untuk proses keluar & pembayaran di pos checkout.
      </div>
    </div>

    <!-- TOMBOL AKSI SELESAI / CETAK -->
    <div class="ticket-actions-row">
      <button type="button" class="btn-ticket-print" onclick="window.print()">
        🖨️ Cetak Struk
      </button>
      <a href="kiosk.php" class="btn-ticket-new">
        🚗 Kendaraan Masuk (Reset) →
      </a>
    </div>

  <?php else: ?>
    <!-- ============================================================
         FORMULIR TERMINAL PARKIR OTOMATIS
         ============================================================ -->
    <form action="kiosk_proses.php" method="post" autocomplete="off" id="formKiosk">
      <!-- 1. KAMERA PEMINDAI ANPR OPTIK OTOMATIS -->
      <div class="anpr-camera-box">
        <div class="anpr-scan-line"></div>
        <div class="anpr-camera-header">
          <span>📷 Kamera ANPR Optical Scanner</span>
          <span class="anpr-live-tag">● LIVE GATE 01</span>
        </div>
        <div class="anpr-detect-row">
          <div>
            <div style="font-size:11px;color:#94a3b8;margin-bottom:3px;">Plat Terdeteksi Otomatis:</div>
            <div class="anpr-plate-display" id="anprPlateTag">F 1428 KLR</div>
          </div>
          <button type="button" class="btn-anpr-reroll" onclick="rerollPlate()">
            🔄 Pindai Plat
          </button>
        </div>
      </div>

      <!-- 2. PEMILIHAN JENIS KENDARAAN (TOUCHSCREEN) -->
      <div class="vehicle-type-grid">
        <div class="vehicle-btn active" id="btnTypeMobil" onclick="selectVehicleType('mobil')">
          <div class="v-icon">🚗</div>
          <div class="v-label">Mobil</div>
          <div class="v-slot"><?= (int)$slot_mobil_count ?> slot kosong</div>
        </div>
        <div class="vehicle-btn" id="btnTypeMotor" onclick="selectVehicleType('motor')">
          <div class="v-icon">🏍️</div>
          <div class="v-label">Motor</div>
          <div class="v-slot"><?= (int)$slot_motor_count ?> slot kosong</div>
        </div>
      </div>

      <!-- Hidden form input jenis -->
      <input type="hidden" name="jenis_kendaraan" id="jenis" value="mobil">

      <!-- 3. INPUT PLAT NOMOR (TERISI OTOMATIS OLEH ANPR) -->
      <div class="form-group-kiosk">
        <label for="inputPlat">
          <span>🚘 Nomor Plat Kendaraan</span>
          <span style="font-size:10.5px;color:#38bdf8;font-weight:600;">Otomatis OCR / Edit Manual</span>
        </label>
        <input type="text" name="plat_nomor" id="inputPlat" required
               class="plate-input-kiosk" value="F 1428 KLR"
               maxlength="12" autofocus>
      </div>

      <!-- 4. REKOMENDASI SLOT OTOMATIS SISTEM -->
      <div class="auto-slot-card">
        <div>
          <span style="color:#94a3b8;font-size:11.5px;display:block;">⚡ Alokasi Bay Otomatis:</span>
          <b id="autoSlotLabel"><?= e($slot_mobil_rek) ?></b>
        </div>
        <span style="background:rgba(16,185,129,0.2);color:#34d399;padding:3px 8px;border-radius:6px;font-size:11px;font-weight:800;">
          Tersedia
        </span>
      </div>

      <!-- Hidden slot ID to let kiosk_proses allocate the best available bay -->
      <input type="hidden" name="id_slot" id="selectedSlotId" value="0">

      <!-- 5. TOMBOL EKSEKUSI TIKET & BUKA PALANG -->
      <button type="submit" class="btn-auto-submit">
        <span>🟢</span>
        <span>TEKAN UNTUK TIKET & BUKA GERBANG</span>
      </button>
    </form>

    <div class="auth-footer">
      <p>Terminal mandiri otomatis didukung oleh sensor loop & ANPR 4K.</p>
      <div class="auth-links">
        <a href="<?= BASE_URL ?>login.php">🔐 Login Petugas Gerbang</a>
        <span aria-hidden="true">·</span>
        <a href="<?= BASE_URL ?>index.php">← Kembali ke Beranda</a>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
// Database slot data
window.__slotMobilRek = <?= json_encode($slot_mobil_rek) ?>;
window.__slotMotorRek = <?= json_encode($slot_motor_rek) ?>;

// Audio Chime notification on gate open
function playGateChime() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const now = ctx.currentTime;
    
    // Tone 1
    const osc1 = ctx.createOscillator();
    const gain1 = ctx.createGain();
    osc1.frequency.setValueAtTime(587.33, now); // D5
    gain1.gain.setValueAtTime(0.15, now);
    gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
    osc1.connect(gain1);
    gain1.connect(ctx.destination);
    osc1.start(now);
    osc1.stop(now + 0.4);

    // Tone 2 (Higher)
    const osc2 = ctx.createOscillator();
    const gain2 = ctx.createGain();
    osc2.frequency.setValueAtTime(880.00, now + 0.15); // A5
    gain2.gain.setValueAtTime(0.2, now + 0.15);
    gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
    osc2.connect(gain2);
    gain2.connect(ctx.destination);
    osc2.start(now + 0.15);
    osc2.stop(now + 0.6);
  } catch(e) {}
}

// Sample ANPR Plate pool for simulation
const samplePlates = ['F 1428 KLR', 'B 8920 PQR', 'D 4519 ZXA', 'B 3021 VBL', 'F 7721 NXZ', 'B 1984 TYU', 'F 2341 BGR'];
let plateIndex = 0;

function rerollPlate() {
  plateIndex = (plateIndex + 1) % samplePlates.length;
  const newPlate = samplePlates[plateIndex];
  const tag = document.getElementById('anprPlateTag');
  const input = document.getElementById('inputPlat');
  if (tag) tag.textContent = newPlate;
  if (input) input.value = newPlate;
}

// Select Vehicle Type
function selectVehicleType(type) {
  const btnMobil = document.getElementById('btnTypeMobil');
  const btnMotor = document.getElementById('btnTypeMotor');
  const inputJenis = document.getElementById('jenis');
  const slotLabel = document.getElementById('autoSlotLabel');

  if (btnMobil) btnMobil.classList.toggle('active', type === 'mobil');
  if (btnMotor) btnMotor.classList.toggle('active', type === 'motor');
  if (inputJenis) inputJenis.value = type;

  if (slotLabel) {
    slotLabel.textContent = type === 'mobil' ? window.__slotMobilRek : window.__slotMotorRek;
  }
}

// Auto Reset Timer if Ticket is Open
<?php if ($tiket): ?>
document.addEventListener('DOMContentLoaded', function() {
  playGateChime();
  let timeLeft = 10;
  const timerEl = document.getElementById('timerReset');
  const interval = setInterval(function() {
    timeLeft--;
    if (timerEl) timerEl.textContent = timeLeft + 's';
    if (timeLeft <= 0) {
      clearInterval(interval);
      window.location.href = 'kiosk.php';
    }
  }, 1000);
});
<?php endif; ?>
</script>
</body>
</html>
