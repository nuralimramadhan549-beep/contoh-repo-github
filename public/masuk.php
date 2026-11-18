<?php
// ============================================================
// PARKIR MASUK - PELANGGAN SELF-SERVICE
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_pelanggan();

$page_title = 'Parkir Sekarang';
$page_subtitle = 'Ambil tiket parkir untuk kendaraan Anda';
$uid = user_id();

$errors = [];
$success = null;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plat = validasi_plat($_POST['plat_nomor'] ?? '');
    $jenis = $_POST['jenis_kendaraan'] ?? '';
    $id_slot = (int)($_POST['id_slot'] ?? 0);

    if (!$plat) $errors[] = 'Format plat nomor tidak valid.';
    if (!in_array($jenis, ['motor','mobil'])) $errors[] = 'Pilih jenis kendaraan.';
    if ($id_slot <= 0) $errors[] = 'Pilih slot parkir.';

    // Cek plat aktif
    if ($plat && !$errors) {
        if (plat_sedang_parkir($koneksi, $plat)) {
            $errors[] = "Kendaraan <b>$plat</b> masih terparkir. Selesaikan dulu.";
        }
    }

    // Validasi slot
    if (!$errors) {
        $stmt = $koneksi->prepare("SELECT * FROM slot_parkir WHERE id_slot=? AND status='kosong' LIMIT 1");
        $stmt->bind_param('i', $id_slot);
        $stmt->execute();
        $slot = $stmt->get_result()->fetch_assoc();
        if (!$slot) {
            $errors[] = 'Slot tidak tersedia.';
        } elseif ($slot['jenis_kendaraan'] !== $jenis) {
            $errors[] = 'Slot tidak sesuai jenis kendaraan.';
        }
    }

    // Insert transaksi
    if (!$errors) {
        $koneksi->begin_transaction();
        try {
            $kode = generate_kode_tiket($koneksi);
            $stmt = $koneksi->prepare("INSERT INTO transaksi_parkir 
                (id_slot, jenis_kendaraan, plat_nomor, user_id, kode_tiket, sumber, via_kiosk) 
                VALUES (?,?,?,?,?,'pelanggan',0)");
            $stmt->bind_param('issis', $id_slot, $jenis, $plat, $uid, $kode);
            $stmt->execute();
            $id_trx = $koneksi->insert_id;

            $koneksi->query("UPDATE slot_parkir SET status='terisi' WHERE id_slot=$id_slot");
            log_aktivitas($koneksi, $uid, 'pelanggan_parkir_masuk', "Plat: $plat, Kode: $kode");
            $koneksi->commit();

            flash('success', "Berhasil! Kode tiket Anda: <b>$kode</b>");
            redirect(PATH_PUBLIC . 'riwayat.php?highlight=' . $id_trx);
        } catch (Exception $ex) {
            $koneksi->rollback();
            $errors[] = 'Gagal: ' . $ex->getMessage();
        }
    }
}

// Ambil kendaraan pelanggan
$stmt = $koneksi->prepare("SELECT * FROM kendaraan_pelanggan WHERE user_id=? ORDER BY is_default DESC");
$stmt->bind_param('i', $uid);
$stmt->execute();
$kendaraan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Ambil slot kosong
$slots = [];
$q = $koneksi->query("SELECT id_slot, kode_slot, jenis_kendaraan, zona FROM slot_parkir WHERE status='kosong' ORDER BY jenis_kendaraan, kode_slot");
while ($r = $q->fetch_assoc()) $slots[] = $r;

// Pre-select plat dari URL
$pre_plat = $_GET['plat'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<h2 style="margin-bottom:6px;">🅿️ Parkir Sekarang</h2>
<p style="color:#64748b;margin-bottom:20px;">Pilih kendaraan dan slot kosong untuk mulai parkir.</p>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <span class="alert-icon">❌</span>
    <div><?php foreach ($errors as $er): ?><div>• <?= $er ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<?php if (empty($kendaraan)): ?>
  <div class="card text-center" style="padding:60px 24px;">
    <div style="font-size:64px;margin-bottom:16px;">🚗</div>
    <h3 style="color:#64748b;margin-bottom:8px;">Belum ada kendaraan terdaftar</h3>
    <p style="color:#94a3b8;margin-bottom:20px;">Tambahkan kendaraan dulu sebelum parkir.</p>
    <a href="<?= PATH_PUBLIC ?>kendaraan_saya.php" class="btn btn-primary btn-lg">➕ Tambah Kendaraan</a>
  </div>
<?php else: ?>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3>📋 Data Parkir</h3></div>
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>🚘 Plat Nomor <span style="color:#dc2626;">*</span></label>
        <select name="plat_nomor" id="plat" required onchange="updateJenis()">
          <option value="">-- Pilih Kendaraan --</option>
          <?php foreach ($kendaraan as $k): ?>
            <option value="<?= e($k['plat_nomor']) ?>" 
                    data-jenis="<?= e($k['jenis_kendaraan']) ?>"
                    <?= $pre_plat === $k['plat_nomor'] ? 'selected' : '' ?>>
              <?= $k['jenis_kendaraan'] === 'motor' ? '🏍️' : '🚗' ?>
              <?= e($k['plat_nomor']) ?>
              <?= $k['merk'] ? ' - ' . e($k['merk']) : '' ?>
              <?= $k['is_default'] ? ' ⭐' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>🚙 Jenis Kendaraan</label>
        <input type="text" id="jenis_display" readonly style="background:#f8fafc;font-weight:700;">
        <input type="hidden" name="jenis_kendaraan" id="jenis">
      </div>

      <div class="form-group">
        <label>🅿️ Pilih Slot Parkir <span style="color:#dc2626;">*</span></label>
        <select name="id_slot" id="slot" required>
          <option value="">-- Pilih jenis kendaraan dulu --</option>
        </select>
      </div>

      <button type="submit" class="btn btn-success btn-lg btn-block">
        ✅ Ambil Tiket Parkir
      </button>
    </form>
  </div>

  <div class="card">
    <div class="card-header"><h3>📊 Ketersediaan Slot</h3></div>
    <div style="display:flex;gap:16px;margin-bottom:16px;">
      <div style="flex:1;text-align:center;padding:20px;background:#ecfeff;border-radius:12px;">
        <div style="font-size:32px;font-weight:800;color:#0891b2;"><?= slot_tersedia($koneksi, 'motor') ?></div>
        <div style="font-size:12px;color:#155e75;font-weight:700;">🏍️ MOTOR KOSONG</div>
      </div>
      <div style="flex:1;text-align:center;padding:20px;background:#fffbeb;border-radius:12px;">
        <div style="font-size:32px;font-weight:800;color:#d97706;"><?= slot_tersedia($koneksi, 'mobil') ?></div>
        <div style="font-size:12px;color:#92400e;font-weight:700;">🚗 MOBIL KOSONG</div>
      </div>
    </div>

    <h3 style="font-size:14px;color:#475569;margin-bottom:12px;">💰 Tarif Parkir</h3>
    <?php $q = $koneksi->query("SELECT * FROM tarif_parkir"); while ($t = $q->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;">
        <span><?= $t['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?></span>
        <b><?= rupiah($t['tarif_per_jam']) ?>/jam</b>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<script>
const SLOTS = <?= json_encode($slots) ?>;
const KENDARAAN = <?= json_encode($kendaraan) ?>;

function updateJenis() {
  const platSel = document.getElementById('plat');
  const opt = platSel.options[platSel.selectedIndex];
  const jenis = opt?.dataset?.jenis || '';
  
  document.getElementById('jenis_display').value = jenis ? (jenis === 'motor' ? '🏍️ Motor' : '🚗 Mobil') : '';
  document.getElementById('jenis').value = jenis;

  // Filter slot
  const slotSel = document.getElementById('slot');
  slotSel.innerHTML = '<option value="">-- Pilih Slot --</option>';
  if (jenis) {
    SLOTS.filter(s => s.jenis_kendaraan === jenis).forEach(s => {
      const o = document.createElement('option');
      o.value = s.id_slot;
      o.textContent = s.kode_slot + ' (Zona ' + (s.zona || 'A') + ')';
      slotSel.appendChild(o);
    });
  }
}

// Auto-run jika ada pre-select
document.addEventListener('DOMContentLoaded', function() {
  if (document.getElementById('plat').value) updateJenis();
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>