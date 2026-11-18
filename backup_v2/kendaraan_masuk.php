<?php
require_once 'includes/auth.php';
cek_login();

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plat = validasi_plat($_POST['plat_nomor'] ?? '');
    $jenis = $_POST['jenis_kendaraan'] ?? '';
    $id_slot = (int)($_POST['id_slot'] ?? 0);

    if (!$plat) $errors[] = 'Format plat nomor tidak valid (contoh: B 1234 XYZ).';
    if (!in_array($jenis, ['motor','mobil'])) $errors[] = 'Pilih jenis kendaraan.';
    if ($id_slot <= 0) $errors[] = 'Pilih slot parkir.';

    // Cek plat masih parkir
    if ($plat && plat_sedang_parkir($koneksi, $plat)) {
        $errors[] = "Kendaraan <b>$plat</b> masih terparkir. Selesaikan dulu transaksinya.";
    }

    // Validasi slot
    if (!$errors && $id_slot > 0) {
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
            $op = user_id();

            $stmt = $koneksi->prepare("INSERT INTO transaksi_parkir 
                (id_slot, jenis_kendaraan, plat_nomor, operator_id, kode_tiket, via_kiosk) 
                VALUES (?,?,?,?,?,0)");
            $stmt->bind_param('issis', $id_slot, $jenis, $plat, $op, $kode);
            $stmt->execute();
            $id_trx = $koneksi->insert_id;

            $koneksi->query("UPDATE slot_parkir SET status='terisi' WHERE id_slot=$id_slot");

            log_aktivitas($koneksi, $op, 'kendaraan_masuk', "Plat: $plat, Kode: $kode");

            $koneksi->commit();
            flash('success', "Kendaraan $plat berhasil masuk. Kode tiket: <b>$kode</b>");
            redirect('struk.php?id=' . $id_trx . '&tipe=masuk');
        } catch (Exception $ex) {
            $koneksi->rollback();
            $errors[] = 'Gagal menyimpan: ' . $ex->getMessage();
        }
    }
}

// Ambil slot kosong
$slots = [];
$q = $koneksi->query("SELECT id_slot, kode_slot, jenis_kendaraan, zona FROM slot_parkir WHERE status='kosong' ORDER BY jenis_kendaraan, kode_slot");
while ($r = $q->fetch_assoc()) $slots[] = $r;

include 'includes/header.php';
?>
<h2>➕ Kendaraan Masuk</h2>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $er): ?><div>• <?= $er ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns:2fr 1fr;">
  <div class="card">
    <form method="post" autocomplete="off">
      <div class="form-group">
        <label>🚘 Plat Nomor</label>
        <input type="text" name="plat_nomor" required placeholder="B 1234 XYZ"
               value="<?= e($_POST['plat_nomor'] ?? '') ?>"
               style="text-transform:uppercase;font-size:18px;font-weight:700;letter-spacing:2px;">
      </div>
      <div class="form-group">
        <label>🚙 Jenis Kendaraan</label>
        <select name="jenis_kendaraan" id="jenis" required onchange="filterSlot(this.value,'slot')">
          <option value="">-- Pilih --</option>
          <option value="motor" <?= ($_POST['jenis_kendaraan'] ?? '')==='motor'?'selected':'' ?>>🏍️ Motor</option>
          <option value="mobil" <?= ($_POST['jenis_kendaraan'] ?? '')==='mobil'?'selected':'' ?>>🚗 Mobil</option>
        </select>
      </div>
      <div class="form-group">
        <label>🅿️ Slot Parkir</label>
        <select name="id_slot" id="slot" required>
          <option value="">-- Pilih jenis dulu --</option>
        </select>
      </div>
      <button class="btn btn-success btn-lg" type="submit">✅ Simpan & Cetak Tiket</button>
    </form>
  </div>

  <div class="card">
    <h3>📊 Ketersediaan Slot</h3>
    <p style="margin-bottom:12px;">🏍️ Motor: <b><?= slot_tersedia($koneksi,'motor') ?> slot</b></p>
    <p style="margin-bottom:16px;">🚗 Mobil: <b><?= slot_tersedia($koneksi,'mobil') ?> slot</b></p>
    <h3 style="margin-top:16px;">💰 Tarif</h3>
    <?php $q = $koneksi->query("SELECT * FROM tarif_parkir"); while($t = $q->fetch_assoc()): ?>
      <p style="margin-bottom:6px;">
        <?= $t['jenis_kendaraan']==='motor'?'🏍️':'🚗' ?> 
        <b><?= ucfirst($t['jenis_kendaraan']) ?></b>: <?= rupiah($t['tarif_per_jam']) ?>/jam
      </p>
    <?php endwhile; ?>
  </div>
</div>

<script>window.__slots = <?= json_encode($slots) ?>;</script>
<script>
// Pre-select jika sebelumnya submit gagal
<?php if (!empty($_POST['jenis_kendaraan'])): ?>
  filterSlot('<?= e($_POST['jenis_kendaraan']) ?>','slot');
  document.getElementById('slot').value = '<?= (int)($_POST['id_slot'] ?? 0) ?>';
<?php endif; ?>
</script>
<?php include 'includes/footer.php'; ?>