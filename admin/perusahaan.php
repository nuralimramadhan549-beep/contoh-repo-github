<?php
// ============================================================
// PROFIL PERUSAHAAN (PT) - ADMIN (ENTERPRISE EDITION)
// Theme: Obsidian Dark (#090d16 / #111827) matching landing page
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Profil Perusahaan';
$page_subtitle = 'Informasi Badan Usaha & Legalitas PT. Parkir Bogor';
$uid = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama   = trim($_POST['nama_perusahaan'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $telp   = trim($_POST['telepon'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $npwp   = trim($_POST['npwp'] ?? '');
    $jam    = trim($_POST['jam_operasional'] ?? '');

    try {
        $stmt = $koneksi->prepare("UPDATE perusahaan SET nama_perusahaan=?, alamat=?, telepon=?, email=?, npwp=?, jam_operasional=? WHERE id_perusahaan=1");
        if ($stmt) {
            $stmt->bind_param('ssssss', $nama, $alamat, $telp, $email, $npwp, $jam);
            if ($stmt->execute()) {
                unset($_SESSION['perusahaan_cache']);
                log_aktivitas($koneksi, $uid, 'update_perusahaan', 'Profil PT diperbarui');
                flash('success', '✅ Profil perusahaan berhasil diperbarui dan disinkronkan ke seluruh sistem.');
            } else {
                flash('error', 'Gagal memperbarui: ' . $koneksi->error);
            }
        }
    } catch (Exception $e) {
        flash('error', 'Gagal update database: ' . $e->getMessage());
    }
    redirect(PATH_ADMIN . 'perusahaan.php');
}

$data = null;
try {
    $res = $koneksi->query("SELECT * FROM perusahaan LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
    }
} catch (Exception $e) {}

if (!$data) {
    $data = [
        'nama_perusahaan' => 'PT. Parkir Bogor',
        'alamat'          => 'Jl. Pajajaran No. 1, Bogor, Jawa Barat 16143',
        'telepon'         => '0251-123456',
        'email'           => 'info@parkirbogor.co.id',
        'npwp'            => '01.234.567.8-901.000',
        'jam_operasional' => '24 Jam Non-Stop'
    ];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================================
   PROFIL PERUSAHAAN (PT) - OBSIDIAN DARK THEME
   ============================================================ */
.pt-page-header {
  background: linear-gradient(135deg, #0c1222 0%, #111827 50%, #0f172a 100%);
  border: 1px solid rgba(56, 189, 248, 0.25);
  border-radius: 20px;
  padding: 26px 30px;
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}
.pt-page-header h2 {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 4px 0;
  display: flex;
  align-items: center;
  gap: 12px;
}
.pt-page-header p {
  color: #94a3b8;
  margin: 0;
  font-size: 13.5px;
}
.pt-grid-split {
  display: grid;
  grid-template-columns: 1.25fr 1fr;
  gap: 24px;
  align-items: start;
}
@media (max-width: 980px) {
  .pt-grid-split { grid-template-columns: 1fr; }
}

.pt-card-dark {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 26px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
}
.pt-card-title {
  font-size: 16px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 6px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.pt-card-subtitle {
  font-size: 13px;
  color: #94a3b8;
  margin-bottom: 22px;
}

.form-group-dark {
  margin-bottom: 18px;
}
.form-group-dark label {
  display: block;
  font-size: 12.5px;
  font-weight: 700;
  color: #cbd5e1;
  margin-bottom: 8px;
  letter-spacing: 0.2px;
}
.form-group-dark input,
.form-group-dark textarea {
  width: 100%;
  padding: 12px 16px;
  border-radius: 10px;
  background: #090d16;
  border: 1px solid #1e293b;
  color: #f1f5f9;
  font-size: 13.5px;
  transition: all 0.2s ease;
  font-family: inherit;
  box-sizing: border-box;
}
.form-group-dark input:focus,
.form-group-dark textarea:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 12px rgba(56, 189, 248, 0.25);
  background: #0c1220;
}
.form-row-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 600px) {
  .form-row-2col { grid-template-columns: 1fr; }
}

/* LIVE PREVIEW CARD */
.pt-preview-box {
  background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(9, 13, 22, 0.9) 100%);
  border: 1px solid rgba(56, 189, 248, 0.2);
  border-radius: 16px;
  padding: 22px;
  margin-bottom: 20px;
}
.preview-badge-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.preview-receipt-sample {
  background: #ffffff;
  color: #0f172a;
  border-radius: 12px;
  padding: 20px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
  position: relative;
}
.receipt-cut-edge {
  border-bottom: 2px dashed #cbd5e1;
  padding-bottom: 12px;
  margin-bottom: 12px;
  text-align: center;
}
.receipt-cut-edge b {
  font-size: 14px;
  color: #0f172a;
  display: block;
}
.receipt-line {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
  font-size: 11.5px;
}
</style>

<div class="pt-page-header">
  <div>
    <h2>
      <span>🏢 Profil Resmi Perusahaan</span>
      <span style="color:#38bdf8;font-size:16px;font-weight:700;">(PT. Parkir)</span>
    </h2>
    <p>Pengaturan identitas resmi perusahaan, legalitas NPWP, kontak pos gerbang, dan informasi kop struk tiket.</p>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= PATH_ADMIN ?>dashboard.php" class="btn btn-sm btn-outline" style="border-color:#334155;color:#cbd5e1;">
      ← Kembali ke Dashboard
    </a>
  </div>
</div>

<div class="pt-grid-split">
  <!-- FORM PENGATURAN PROFIL -->
  <div class="pt-card-dark">
    <h3 class="pt-card-title">📝 Formulir Identitas PT</h3>
    <div class="pt-card-subtitle">Perubahan data akan otomatis tersinkron ke struk parkir, header sistem, dan dashboard admin.</div>

    <form method="post">
      <div class="form-row-2col">
        <div class="form-group-dark">
          <label>🏢 Nama Resmi Perusahaan *</label>
          <input type="text" name="nama_perusahaan" required value="<?= e($data['nama_perusahaan']) ?>" placeholder="PT. Parkir Bogor">
        </div>
        <div class="form-group-dark">
          <label>📞 Telepon / Hotline Layanan</label>
          <input type="text" name="telepon" value="<?= e($data['telepon']) ?>" placeholder="0251-123456">
        </div>
      </div>

      <div class="form-row-2col">
        <div class="form-group-dark">
          <label>✉️ Email Resmi Korespondensi</label>
          <input type="email" name="email" value="<?= e($data['email']) ?>" placeholder="info@parkirbogor.co.id">
        </div>
        <div class="form-group-dark">
          <label>📋 Nomor Pokok Wajib Pajak (NPWP)</label>
          <input type="text" name="npwp" value="<?= e($data['npwp']) ?>" placeholder="01.234.567.8-901.000">
        </div>
      </div>

      <div class="form-group-dark">
        <label>🏠 Alamat Kantor Operasional</label>
        <textarea name="alamat" rows="3" placeholder="Jl. Pajajaran No. 1, Kota Bogor, Jawa Barat 16143"><?= e($data['alamat']) ?></textarea>
      </div>

      <div class="form-group-dark">
        <label>🕐 Jam Operasional Pelayanan</label>
        <input type="text" name="jam_operasional" value="<?= e($data['jam_operasional']) ?>" placeholder="24 Jam Non-Stop / 06:00 - 22:00">
      </div>

      <div style="margin-top:24px;display:flex;gap:12px;align-items:center;">
        <button type="submit" class="btn btn-primary btn-lg" style="box-shadow:0 4px 16px rgba(37,99,235,0.4);font-weight:700;">
          💾 Simpan Perubahan Profil PT
        </button>
        <a href="<?= PATH_ADMIN ?>dashboard.php" class="btn btn-outline" style="border-color:#334155;color:#94a3b8;">
          Batal
        </a>
      </div>
    </form>
  </div>

  <!-- PREVIEW TAMPILAN DI TIKET & DASHBOARD -->
  <div class="pt-card-dark">
    <h3 class="pt-card-title">👁️ Pratinjau Kop Tiket / Struk QR</h3>
    <div class="pt-card-subtitle">Contoh tampilan data profil PT pada kertas struk fisik yang dicetak oleh gerbang operator:</div>

    <div class="preview-receipt-sample">
      <div class="receipt-cut-edge">
        <b><?= e($data['nama_perusahaan']) ?></b>
        <div style="font-size:11px;color:#475569;margin-top:2px;"><?= e($data['alamat']) ?></div>
        <div style="font-size:11px;color:#475569;">Telp: <?= e($data['telepon']) ?> | NPWP: <?= e($data['npwp']) ?></div>
      </div>

      <div style="text-align:center;font-weight:700;font-size:13px;margin-bottom:8px;letter-spacing:1px;">
        *** TIKET PARKIR RESMI ***
      </div>

      <div class="receipt-line">
        <span>No. Tiket:</span>
        <b>PB-20261105-001</b>
      </div>
      <div class="receipt-line">
        <span>Plat Nomor:</span>
        <b>F 1428 KLR</b>
      </div>
      <div class="receipt-line">
        <span>Jam Operasional:</span>
        <span><?= e($data['jam_operasional']) ?></span>
      </div>
      <div class="receipt-line">
        <span>Pos Gerbang:</span>
        <span>Gate 01 In (ANPR 4K)</span>
      </div>

      <div style="margin:16px 0;text-align:center;padding:10px;background:#f8fafc;border-radius:8px;border:1px dashed #cbd5e1;">
        <div style="font-size:28px;">🏁 📱 🎫</div>
        <small style="color:#64748b;font-size:10px;">[ QR CODE SCANNER COMPATIBLE ]</small>
      </div>

      <div style="text-align:center;font-size:10.5px;color:#64748b;border-top:1px dashed #cbd5e1;padding-top:8px;">
        Terima kasih atas kunjungan Anda.<br>
        Simpan struk ini sebagai bukti pembayaran sah.
      </div>
    </div>

    <div style="margin-top:20px;background:rgba(56,189,248,0.06);border:1px solid rgba(56,189,248,0.2);border-radius:12px;padding:16px;font-size:12.5px;color:#cbd5e1;line-height:1.6;">
      <b style="color:#38bdf8;display:block;margin-bottom:4px;">💡 Informasi Integrasi Bapenda & Dishub:</b>
      Data NPWP dan nama entitas PT terhubung langsung dengan sistem perpajakan daerah Kota Bogor dan audit laporan pendapatan per shift.
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
