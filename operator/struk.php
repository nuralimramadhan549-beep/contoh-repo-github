<?php
// ============================================================
// STRUK TIKET & PEMBAYARAN - OPERATOR (ENTERPRISE EDITION)
// Standard: Vector QR Code Generation, Offline-Ready, Thermal Print
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_operator();

$id = (int)($_GET['id'] ?? 0);
$tipe = $_GET['tipe'] ?? '';

$stmt = $koneksi->prepare("SELECT t.*, s.kode_slot, s.zona,
    u.nama_lengkap AS operator_nama,
    p.nama_lengkap AS pelanggan_nama,
    pm.total_biaya, pm.denda, pm.metode_pembayaran, pm.bayar, pm.kembalian, pm.waktu_bayar
    FROM transaksi_parkir t
    JOIN slot_parkir s ON t.id_slot = s.id_slot
    LEFT JOIN users u ON t.operator_id = u.id_user
    LEFT JOIN users p ON t.user_id = p.id_user
    LEFT JOIN pembayaran pm ON pm.id_transaksi = t.id_transaksi
    WHERE t.id_transaksi = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();

if (!$trx) {
    flash('error', 'Transaksi tidak ditemukan.');
    redirect(PATH_OPERATOR . 'dashboard.php');
}

// Otomatis tentukan tipe jika tidak dispesifikasikan di URL
if (empty($tipe)) {
    $tipe = ($trx['status'] === 'selesai' || !empty($trx['waktu_keluar'])) ? 'keluar' : 'masuk';
}

$is_masuk = ($tipe === 'masuk');
$judul_tiket = $is_masuk ? 'TIKET PARKIR DIGITAL' : 'STRUK BUKTI PEMBAYARAN';
$sub_tiket = $is_masuk ? 'GATE MASUK // CHECK-IN' : 'GATE KELUAR // CHECK-OUT';

// Generate QR Code SVG (100% Offline & Vector Crisp)
$qr_svg = render_qr_svg($trx['kode_tiket'], 150);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_masuk ? 'Tiket Parkir' : 'Struk Pembayaran' ?> #<?= $trx['id_transaksi'] ?> — <?= e($trx['kode_tiket']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css?v=<?= time() ?>">
<style>
/* ================================================================
   STRUK & TIKET THERMAL RESMI - OPERATOR GATE
   ================================================================ */
body {
  background: #090d16;
  color: #1e293b;
  font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  padding: 30px 16px 60px;
  min-height: 100vh;
}

.struk-container {
  max-width: 400px;
  margin: 0 auto;
}

.struk-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 30px 26px;
  box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1);
  font-family: 'Courier New', Courier, monospace;
  color: #0f172a;
  position: relative;
  overflow: hidden;
}

/* Dekorasi tepi atas struk */
.struk-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 5px;
  background: linear-gradient(90deg, #2563eb, #38bdf8, #10b981);
}

.struk-header {
  text-align: center;
  padding-bottom: 12px;
}
.struk-brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: #2563eb;
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  padding: 3px 10px;
  border-radius: 9999px;
  margin-bottom: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.struk-header h2 {
  font-size: 17px;
  font-weight: 900;
  color: #000;
  margin: 0 0 4px 0;
  letter-spacing: -0.3px;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.struk-header .meta {
  font-size: 11.5px;
  color: #475569;
  line-height: 1.4;
  margin: 0;
}

.struk-divider {
  border: none;
  border-top: 1px dashed #94a3b8;
  margin: 14px 0;
}

.struk-title-box {
  text-align: center;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 8px 12px;
  margin: 10px 0 14px;
}
.struk-title-box .title {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: 0.8px;
  color: #0f172a;
  font-family: 'Plus Jakarta Sans', sans-serif;
}
.struk-title-box .subtitle {
  font-size: 10px;
  color: #64748b;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin-top: 2px;
}

/* KOTAK KODE QR RESMI */
.struk-qr-box {
  background: #f8fafc;
  border: 2px dashed #94a3b8;
  border-radius: 12px;
  padding: 16px 12px 14px;
  text-align: center;
  margin: 14px 0 16px;
}
.struk-qr-graphic {
  background: #ffffff;
  display: inline-block;
  padding: 10px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.struk-qr-graphic svg {
  display: block;
  margin: 0 auto;
}
.struk-qr-code-text {
  font-family: 'JetBrains Mono', monospace;
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 2.5px;
  color: #1e3a8a;
  margin-top: 10px;
}
.struk-qr-instruction {
  font-size: 10.5px;
  color: #64748b;
  font-weight: 700;
  letter-spacing: 0.5px;
  margin-top: 4px;
  text-transform: uppercase;
}

/* TABEL DATA */
.struk-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12.5px;
  margin: 8px 0;
}
.struk-table td {
  padding: 4px 0;
  vertical-align: top;
  border: none;
}
.struk-table .lbl {
  color: #475569;
  width: 42%;
}
.struk-table .val {
  text-align: right;
  color: #0f172a;
  font-weight: 600;
}
.struk-table .val b {
  color: #000;
  font-weight: 800;
}
.struk-table .val.plat {
  font-family: 'JetBrains Mono', monospace;
  font-size: 14px;
  color: #1e3a8a;
}

/* TOTAL PEMBAYARAN BOX */
.struk-total-box {
  background: #f1f5f9;
  border-left: 4px solid #10b981;
  border-radius: 8px;
  padding: 12px 14px;
  margin: 12px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.struk-total-box .lbl {
  font-size: 12px;
  font-weight: 800;
  color: #334155;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.struk-total-box .val {
  font-size: 20px;
  font-weight: 900;
  color: #047857;
  font-family: 'JetBrains Mono', monospace;
}

/* FOOTER NOTIFIKASI */
.struk-footer {
  text-align: center;
  font-size: 11px;
  color: #64748b;
  line-height: 1.5;
  margin-top: 12px;
  padding-top: 10px;
}
.struk-footer .barcode-sim {
  letter-spacing: 4px;
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  margin: 8px 0 6px;
  font-family: 'JetBrains Mono', monospace;
}

/* ACTION BUTTONS (NO-PRINT) */
.struk-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 22px;
}
.struk-btn-row {
  display: flex;
  gap: 10px;
}
.struk-btn-row .btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-weight: 700;
  font-size: 13.5px;
  padding: 12px 16px;
  border-radius: 10px;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
}
.btn-print {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #ffffff;
  border: none;
  box-shadow: 0 4px 14px rgba(37,99,235,0.35);
}
.btn-print:hover {
  background: linear-gradient(135deg, #1d4ed8, #1e40af);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(37,99,235,0.45);
}
.btn-copy {
  background: #1e293b;
  color: #38bdf8;
  border: 1px solid #334155;
}
.btn-copy:hover {
  background: #334155;
  color: #ffffff;
}
.btn-nav {
  background: rgba(255,255,255,0.06);
  color: #cbd5e1;
  border: 1px solid rgba(255,255,255,0.12);
}
.btn-nav:hover {
  background: rgba(255,255,255,0.12);
  color: #ffffff;
}

/* TOAST ALERT */
#copyToast {
  display: none;
  position: fixed;
  bottom: 24px;
  left: 50%;
  transform: translateX(-50%);
  background: #10b981;
  color: #fff;
  padding: 10px 20px;
  border-radius: 9999px;
  font-size: 13px;
  font-weight: 700;
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  z-index: 9999;
}

/* PRINT MEDIA */
@media print {
  body {
    background: #ffffff !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .no-print {
    display: none !important;
  }
  .struk-container {
    max-width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }
  .struk-card {
    box-shadow: none !important;
    border: none !important;
    padding: 10px 14px !important;
    border-radius: 0 !important;
    width: 100% !important;
  }
  .struk-card::before {
    display: none !important;
  }
  .struk-qr-graphic {
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
  }
  * {
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
}
</style>
</head>
<body>

<div class="struk-container">
  <!-- KARTU STRUK / TIKET RESMI -->
  <div class="struk-card">
    <div class="struk-header">
      <div class="struk-brand-badge">🅿️ SMART MOBILITY GATE</div>
      <h2><?= e($perusahaan['nama_perusahaan']) ?></h2>
      <p class="meta">
        <?= e($perusahaan['alamat']) ?><br>
        Telepon: <?= e($perusahaan['telepon']) ?> · Pos Gerbang 01
      </p>
    </div>

    <hr class="struk-divider">

    <div class="struk-title-box">
      <div class="title"><?= $judul_tiket ?></div>
      <div class="subtitle"><?= $sub_tiket ?></div>
    </div>

    <!-- KOTAK KODE QR (OFFLINE & VECTOR CRISP) -->
    <div class="struk-qr-box">
      <div class="struk-qr-graphic">
        <?php if (!empty($qr_svg)): ?>
          <?= $qr_svg ?>
        <?php else: ?>
          <!-- Online Fallback Image jika SVG kosong -->
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($trx['kode_tiket']) ?>" alt="QR Code" width="150" height="150" style="display:block;margin:0 auto;">
        <?php endif; ?>
      </div>
      <div class="struk-qr-code-text"><?= e($trx['kode_tiket']) ?></div>
      <div class="struk-qr-instruction">
        <?= $is_masuk ? '● Tunjukkan / Scan QR ini di Gerbang Keluar ●' : '● Bukti Transaksi Resmi Terverifikasi ●' ?>
      </div>
    </div>

    <!-- TABEL RINCIAN KENDARAAN -->
    <table class="struk-table">
      <tr>
        <td class="lbl">No. Transaksi</td>
        <td class="val">#<?= (int)$trx['id_transaksi'] ?></td>
      </tr>
      <tr>
        <td class="lbl">Plat Nomor</td>
        <td class="val plat"><b><?= e($trx['plat_nomor']) ?></b></td>
      </tr>
      <tr>
        <td class="lbl">Jenis Kendaraan</td>
        <td class="val"><?= $trx['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?></td>
      </tr>
      <tr>
        <td class="lbl">Slot Alokasi</td>
        <td class="val"><b><?= e($trx['kode_slot']) ?></b> (Zona <?= e($trx['zona'] ?? 'A') ?>)</td>
      </tr>
      <tr>
        <td class="lbl">Waktu Masuk</td>
        <td class="val"><?= date('d/m/Y H:i:s', strtotime($trx['waktu_masuk'])) ?></td>
      </tr>
      <?php if (!empty($trx['waktu_keluar'])): ?>
      <tr>
        <td class="lbl">Waktu Keluar</td>
        <td class="val"><?= date('d/m/Y H:i:s', strtotime($trx['waktu_keluar'])) ?></td>
      </tr>
      <tr>
        <td class="lbl">Durasi Parkir</td>
        <td class="val"><b><?= (int)$trx['durasi_jam'] ?> jam</b></td>
      </tr>
      <?php endif; ?>
      <tr>
        <td class="lbl">Petugas Operator</td>
        <td class="val"><?= e($trx['operator_nama'] ?: ($trx['via_kiosk'] ? 'Kiosk Mandiri' : 'Petugas Pos')) ?></td>
      </tr>
      <tr>
        <td class="lbl">Status Pelanggan</td>
        <td class="val"><?= e($trx['pelanggan_nama'] ?: 'Pengunjung Umum (Walk-in)') ?></td>
      </tr>
    </table>

    <!-- RINCIAN BIAYA JIKA KELUAR / BAYAR -->
    <?php if (isset($trx['total_biaya']) && $trx['total_biaya'] !== null): ?>
      <hr class="struk-divider">
      <table class="struk-table">
        <tr>
          <td class="lbl">Tarif Parkir</td>
          <td class="val"><?= rupiah($trx['total_biaya']) ?></td>
        </tr>
        <?php if ($trx['denda'] > 0): ?>
        <tr>
          <td class="lbl" style="color:#dc2626;">Denda Tiket Hilang</td>
          <td class="val" style="color:#dc2626;"><?= rupiah($trx['denda']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td class="lbl"><b>Metode Bayar</b></td>
          <td class="val"><b><?= strtoupper($trx['metode_pembayaran'] ?? 'TUNAI') ?></b></td>
        </tr>
        <tr>
          <td class="lbl">Uang Diterima</td>
          <td class="val"><?= rupiah($trx['bayar'] ?? ($trx['total_biaya'] + $trx['denda'])) ?></td>
        </tr>
        <?php if (isset($trx['kembalian'])): ?>
        <tr>
          <td class="lbl">Kembalian</td>
          <td class="val"><?= rupiah($trx['kembalian']) ?></td>
        </tr>
        <?php endif; ?>
      </table>

      <div class="struk-total-box">
        <span class="lbl">Total Bayar:</span>
        <span class="val"><?= rupiah($trx['total_biaya'] + $trx['denda']) ?></span>
      </div>
    <?php endif; ?>

    <hr class="struk-divider">

    <div class="struk-footer">
      <div class="barcode-sim">||| | | |||| | ||||| | |||</div>
      <div>Waktu Cetak: <?= date('d/m/Y H:i:s') ?> WIB</div>
      <div style="font-weight:700;margin:6px 0 4px;color:#0f172a;">*** TERIMA KASIH ATAS KUNJUNGAN ANDA ***</div>
      <div>Simpan tiket ini dengan aman. Kehilangan karcis/tiket parkir akan dikenakan sanksi denda administrasi.</div>
    </div>
  </div>

  <!-- TOOLBAR AKSI (TIDAK TERCETAK) -->
  <div class="struk-actions no-print">
    <div class="struk-btn-row">
      <button type="button" class="btn btn-print" onclick="window.print()">
        🖨️ Cetak Struk / Tiket
      </button>
      <button type="button" class="btn btn-copy" onclick="copyKodeTiket('<?= e($trx['kode_tiket']) ?>')">
        📋 Salin Kode
      </button>
    </div>
    <div class="struk-btn-row">
      <a href="<?= PATH_OPERATOR ?>dashboard.php" class="btn btn-nav">
        🏠 Dashboard
      </a>
      <?php if ($is_masuk): ?>
        <a href="<?= PATH_OPERATOR ?>masuk.php" class="btn btn-nav" style="color:#34d399;border-color:rgba(52,211,153,0.3);">
          ➕ Input Masuk Lagi
        </a>
      <?php else: ?>
        <a href="<?= PATH_OPERATOR ?>keluar.php" class="btn btn-nav" style="color:#fbbf24;border-color:rgba(245,158,11,0.3);">
          🚙 Proses Keluar Lagi
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="copyToast">✅ Kode tiket berhasil disalin!</div>

<script>
function copyKodeTiket(kode) {
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(kode).then(showToast);
  } else {
    const ta = document.createElement('textarea');
    ta.value = kode;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    showToast();
  }
}
function showToast() {
  const t = document.getElementById('copyToast');
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 2200);
}
</script>
</body>
</html>
