<?php
// ============================================================
// INPUT MASUK - OPERATOR
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_operator();

$page_title = 'Input Kendaraan Masuk';
$page_subtitle = 'Catat kendaraan yang masuk area parkir';

$errors = [];
$success = null;
$uid = user_id();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plat = validasi_plat($_POST['plat_nomor'] ?? '');
    $jenis = $_POST['jenis_kendaraan'] ?? '';
    $id_slot = (int)($_POST['id_slot'] ?? 0);

    if (!$plat) $errors[] = 'Format plat nomor tidak valid.';
    if (!in_array($jenis, ['motor','mobil'])) $errors[] = 'Pilih jenis kendaraan.';
    if ($id_slot <= 0) $errors[] = 'Pilih slot parkir.';

    if ($plat && !$errors) {
        if (plat_sedang_parkir($koneksi, $plat)) {
            $errors[] = "Kendaraan <b>$plat</b> masih terparkir.";
        }
    }

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

    if (!$errors) {
        $koneksi->begin_transaction();
        try {
            $kode = generate_kode_tiket($koneksi);
            $stmt = $koneksi->prepare("INSERT INTO transaksi_parkir 
                (id_slot, jenis_kendaraan, plat_nomor, operator_id, kode_tiket, sumber) 
                VALUES (?,?,?,?,?,'operator')");
            $stmt->bind_param('issis', $id_slot, $jenis, $plat, $uid, $kode);
            $stmt->execute();
            $id_trx = $koneksi->insert_id;

            $koneksi->query("UPDATE slot_parkir SET status='terisi' WHERE id_slot=$id_slot");
            log_aktivitas($koneksi, $uid, 'operator_input_masuk', "Plat: $plat, Kode: $kode");
            $koneksi->commit();

            flash('success', "Kendaraan <b>$plat</b> berhasil masuk. Kode tiket: <b>$kode</b>");
            redirect(PATH_OPERATOR . 'struk.php?id=' . $id_trx . '&tipe=masuk');
        } catch (Exception $ex) {
            $koneksi->rollback();
            $errors[] = 'Gagal: ' . $ex->getMessage();
        }
    }
}

// Slot kosong
$slots = [];
$q = $koneksi->query("SELECT id_slot, kode_slot, jenis_kendaraan, zona FROM slot_parkir WHERE status='kosong' ORDER BY jenis_kendaraan, kode_slot");
while ($r = $q->fetch_assoc()) $slots[] = $r;

include __DIR__ . '/../includes/header.php';
?>

<h2 style="margin-bottom:6px;">➕ Input Kendaraan Masuk</h2>
<p style="color:#64748b;margin-bottom:20px;">Catat kendaraan walk-in yang masuk area parkir.</p>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <span class="alert-icon">❌</span>
    <div><?php foreach ($errors as $er): ?><div>• <?= $er ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3>📝 Form Input</h3></div>
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>🚘 Plat Nomor <span style="color:#dc2626;">*</span></label>
        <input type="text" name="plat_nomor" required autofocus
               value="<?= e($_POST['plat_nomor'] ?? '') ?>"
               placeholder="B 1234 XYZ"
               style="text-transform:uppercase;font-weight:700;letter-spacing:2px;font-size:18px;">
      </div>

      <div class="form-group">
        <label>🚙 Jenis Kendaraan <span style="color:#dc2626;">*</span></label>
        <select name="jenis_kendaraan" id="jenis" required onchange="filterSlot(this.value, 'slot')">
          <option value="">-- Pilih Jenis --</option>
          <option value="motor" <?= ($_POST['jenis_kendaraan'] ?? '') === 'motor' ? 'selected' : '' ?>>🏍️ Motor</option>
          <option value="mobil" <?= ($_POST['jenis_kendaraan'] ?? '') === 'mobil' ? 'selected' : '' ?>>🚗 Mobil</option>
        </select>
      </div>

      <div class="form-group">
        <label>🅿️ Slot Parkir <span style="color:#dc2626;">*</span></label>
        <select name="id_slot" id="slot" required>
          <option value="">-- Pilih jenis dulu --</option>
        </select>
      </div>

      <button type="submit" class="btn btn-success btn-lg btn-block">
        ✅ Simpan & Cetak Tiket
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

    <h3 style="font-size:14px;color:#475569;margin-bottom:12px;">💰 Tarif</h3>
    <?php $q = $koneksi->query("SELECT * FROM tarif_parkir"); while ($t = $q->fetch_assoc()): ?>
      <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;">
        <span><?= $t['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?></span>
        <b><?= rupiah($t['tarif_per_jam']) ?>/jam</b>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<script>window.__slots = <?= json_encode($slots) ?>;</script>
<script>
<?php if (!empty($_POST['jenis_kendaraan'])): ?>
  filterSlot('<?= e($_POST['jenis_kendaraan']) ?>', 'slot');
  document.getElementById('slot').value = '<?= (int)($_POST['id_slot'] ?? 0) ?>';
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>