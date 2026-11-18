<?php
// ============================================================
// AJUKAN KELUAR - PELANGGAN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_pelanggan();

$page_title = 'Ajukan Keluar';
$page_subtitle = 'Ajukan permohonan keluar — operator akan memverifikasi';
$uid = user_id();

$errors = [];
$cari = null;
$biaya_info = null;

// STEP 1: Cari dari POST atau GET
$id_trx_req = (int)($_POST['id_transaksi'] ?? ($_GET['id'] ?? 0));
if ((isset($_POST['cari']) || isset($_GET['id'])) && $id_trx_req > 0) {
    $stmt = $koneksi->prepare("SELECT t.*, s.kode_slot, s.zona 
        FROM transaksi_parkir t 
        JOIN slot_parkir s ON t.id_slot=s.id_slot 
        WHERE t.id_transaksi=? AND t.user_id=? AND t.waktu_keluar IS NULL 
        AND t.status IN ('aktif', 'menunggu_approval') LIMIT 1");
    $stmt->bind_param('ii', $id_trx_req, $uid);
    $stmt->execute();
    $cari = $stmt->get_result()->fetch_assoc();
    
    if (!$cari) {
        $errors[] = 'Transaksi tidak ditemukan atau bukan milik Anda.';
    } else {
        $biaya_info = hitung_biaya($koneksi, $cari['jenis_kendaraan'], $cari['waktu_masuk']);
    }
} elseif (isset($_POST['cari']) && $id_trx_req <= 0) {
    $errors[] = 'Pilih kendaraan Anda.';
}

// STEP 2: Ajukan keluar
if (isset($_POST['ajukan'])) {
    $id_trx = (int)($_POST['id_transaksi'] ?? 0);

    $stmt = $koneksi->prepare("SELECT * FROM transaksi_parkir 
        WHERE id_transaksi=? AND user_id=? AND waktu_keluar IS NULL 
        AND status='aktif' LIMIT 1");
    $stmt->bind_param('ii', $id_trx, $uid);
    $stmt->execute();
    $trx = $stmt->get_result()->fetch_assoc();

    if (!$trx) {
        $errors[] = 'Transaksi tidak valid atau sudah diajukan.';
    } else {
        $stmt = $koneksi->prepare("UPDATE transaksi_parkir 
            SET status='menunggu_approval', waktu_ajuan_keluar=NOW() 
            WHERE id_transaksi=?");
        $stmt->bind_param('i', $id_trx);
        
        if ($stmt->execute()) {
            log_aktivitas($koneksi, $uid, 'pelanggan_ajukan_keluar', 
                "Plat: {$trx['plat_nomor']}, Kode: {$trx['kode_tiket']}");
            flash('success', 'Permohonan keluar dikirim! <b>Silakan tunggu konfirmasi operator di pos parkir.</b>');
            redirect(PATH_PUBLIC . 'riwayat.php');
        } else {
            $errors[] = 'Gagal mengajukan: ' . $koneksi->error;
        }
    }
}

// Ambil kendaraan aktif pelanggan
$stmt = $koneksi->prepare("SELECT t.*, s.kode_slot, s.zona 
    FROM transaksi_parkir t 
    JOIN slot_parkir s ON t.id_slot=s.id_slot 
    WHERE t.user_id=? AND t.waktu_keluar IS NULL 
    AND t.status IN ('aktif', 'menunggu_approval')
    ORDER BY t.waktu_masuk DESC");
$stmt->bind_param('i', $uid);
$stmt->execute();
$aktif = $stmt->get_result();

include __DIR__ . '/../includes/header.php';
?>

<style>
.flow-info {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-left: 4px solid #f59e0b;
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 24px;
  display: flex;
  gap: 16px;
  align-items: flex-start;
}
.flow-info-icon { font-size: 32px; flex-shrink: 0; }
.flow-info-text h3 { font-size: 15px; font-weight: 800; color: #fbbf24; margin: 0 0 8px 0; }
.flow-info-text ol { margin: 0; padding-left: 20px; color: #e2e8f0; font-size: 13.5px; line-height: 1.8; }
.flow-info-text b { color: #fde68a; }

.vehicle-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  background: #131c2e;
  border: 1px solid #1e293b;
  border-radius: 12px;
  gap: 16px;
  flex-wrap: wrap;
  transition: border-color 0.2s, background 0.2s;
}
.vehicle-card:hover {
  background: #182338;
  border-color: #334155;
}
.vehicle-card.waiting {
  background: rgba(245, 158, 11, 0.1);
  border-color: rgba(245, 158, 11, 0.4);
  border-left: 4px solid #f59e0b;
}

@media (max-width: 640px) {
  .flow-info { flex-direction: column; gap: 10px; padding: 16px; }
  .flow-info-icon { font-size: 26px; }
  .vehicle-card { padding: 14px; }
}
</style>

<div class="page-header">
  <div>
    <h2>🚪 Ajukan Keluar</h2>
    <p>Ajukan permohonan keluar — operator akan memverifikasi dan memproses pembayaran Anda.</p>
  </div>
</div>

<!-- Info Alur -->
<div class="flow-info">
  <div class="flow-info-icon">ℹ️</div>
  <div class="flow-info-text">
    <h3>Alur Keluar Parkir</h3>
    <ol>
      <li>Pilih kendaraan dan klik tombol <b>"Ajukan Keluar"</b></li>
      <li>Status tiket akan berubah menjadi <b>"Menunggu Konfirmasi"</b></li>
      <li>Tunjukkan plat nomor atau kode tiket ke <b>operator pos parkir</b></li>
      <li>Operator memverifikasi pembayaran (Tunai / QRIS)</li>
      <li>Palang terbuka dan kendaraan Anda dapat keluar dengan aman</li>
    </ol>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <span class="alert-icon">❌</span>
    <div><?php foreach ($errors as $er): ?><div>• <?= $er ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<?php if ($cari && $biaya_info): ?>
  <!-- KONFIRMASI -->
  <div class="card" style="border-left:4px solid #f59e0b;">
    <div class="card-header"><h3>📋 Konfirmasi Permohonan Keluar</h3></div>
    
    <div class="table-wrapper mb-3">
      <table>
        <tr><td style="width:180px;color:#94a3b8;"><b>Kode Tiket</b></td><td><code style="background:#1e293b;padding:4px 10px;border-radius:6px;font-size:13px;color:#38bdf8;"><?= e($cari['kode_tiket']) ?></code></td></tr>
        <tr><td style="color:#94a3b8;"><b>Plat Nomor</b></td><td><b style="font-size:20px;letter-spacing:1.5px;color:#ffffff;"><?= e($cari['plat_nomor']) ?></b></td></tr>
        <tr><td style="color:#94a3b8;"><b>Jenis Kendaraan</b></td><td><?= $cari['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?></td></tr>
        <tr><td style="color:#94a3b8;"><b>Slot Terparkir</b></td><td><b><?= e($cari['kode_slot']) ?></b> (Zona <?= e($cari['zona'] ?? 'A') ?>)</td></tr>
        <tr><td style="color:#94a3b8;"><b>Durasi Berjalan</b></td><td><b><?= $biaya_info['jam'] ?> Jam</b></td></tr>
        <tr style="background:rgba(245,158,11,0.12);"><td style="color:#fbbf24;"><b>Estimasi Biaya</b></td><td><b style="font-size:22px;color:#fbbf24;"><?= rupiah($biaya_info['total']) ?></b></td></tr>
      </table>
    </div>

    <div class="alert alert-warning" style="margin-bottom:16px;">
      <span class="alert-icon">💰</span>
      <div><b>Pembayaran dilakukan ke kasir/operator pos keluar</b> setelah pengajuan ini dikirim.</div>
    </div>

    <form method="post" onsubmit="return confirm('Ajukan permohonan keluar sekarang? Silakan langsung menuju pos kasir.');">
      <input type="hidden" name="id_transaksi" value="<?= (int)$cari['id_transaksi'] ?>">
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <button type="submit" name="ajukan" value="1" class="btn btn-warning btn-lg" style="box-shadow:0 4px 14px rgba(245,158,11,0.35);">
          📤 Konfirmasi & Ajukan Keluar
        </button>
        <a href="<?= PATH_PUBLIC ?>keluar.php" class="btn btn-secondary btn-lg">Batal</a>
      </div>
    </form>
  </div>

<?php else: ?>

  <?php if ($aktif->num_rows === 0): ?>
    <div class="card text-center" style="padding:60px 24px;">
      <div style="font-size:64px;margin-bottom:16px;">🅿️</div>
      <h3 style="color:#94a3b8;margin-bottom:8px;">Tidak Ada Kendaraan yang Sedang Parkir</h3>
      <p style="color:#64748b;margin-bottom:20px;">Kendaraan Anda belum tercatat masuk atau sudah berhasil keluar.</p>
      <a href="<?= PATH_PUBLIC ?>masuk.php" class="btn btn-success btn-lg">🅿️ Parkir Sekarang</a>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="card-header"><h3>🚗 Pilih Kendaraan yang Akan Keluar</h3></div>
      <div style="display:grid;gap:12px;">
        <?php while ($r = $aktif->fetch_assoc()): 
          $h = hitung_biaya($koneksi, $r['jenis_kendaraan'], $r['waktu_masuk']);
          $is_waiting = $r['status'] === 'menunggu_approval';
        ?>
          <div class="vehicle-card <?= $is_waiting ? 'waiting' : '' ?>">
            <div style="flex:1;min-width:200px;">
              <div style="font-weight:800;font-size:18px;letter-spacing:1px;color:#ffffff;">
                <?= e($r['plat_nomor']) ?>
              </div>
              <div style="color:#94a3b8;font-size:12.5px;margin-top:4px;">
                <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?>
                · Slot <b style="color:#f1f5f9;"><?= e($r['kode_slot']) ?></b> (Zona <?= e($r['zona'] ?? 'A') ?>)
                · <?= $h['jam'] ?> jam
              </div>
              <div style="font-size:11.5px;color:#64748b;margin-top:4px;">
                Kode: <code style="color:#38bdf8;"><?= e($r['kode_tiket']) ?></code>
              </div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <div style="font-size:11px;color:#94a3b8;">Estimasi Biaya</div>
              <div style="font-weight:800;font-size:20px;color:#f87171;margin-bottom:10px;">
                <?= rupiah($h['total']) ?>
              </div>
              <?php if ($is_waiting): ?>
                <span class="badge badge-warning" style="padding:8px 14px;font-size:12px;">
                  ⏳ MENUNGGU OPERATOR
                </span>
              <?php else: ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="id_transaksi" value="<?= (int)$r['id_transaksi'] ?>">
                  <button class="btn btn-warning btn-sm" name="cari" value="1">📤 Ajukan Keluar</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>