<?php
require_once 'includes/auth.php';
cek_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $id = (int)$_POST['id_slot'];
    if ($_POST['aksi'] === 'status') {
        $st = $_POST['status'];
        if (in_array($st, ['kosong','terisi','maintenance'])) {
            $koneksi->query("UPDATE slot_parkir SET status='$st' WHERE id_slot=$id");
            flash('success', 'Status slot diperbarui.');
        }
    } elseif ($_POST['aksi'] === 'tambah') {
        $kode = strtoupper(trim($_POST['kode_slot']));
        $jenis = $_POST['jenis'];
        $zona = strtoupper(trim($_POST['zona'] ?? 'A'));
        $stmt = $koneksi->prepare("INSERT INTO slot_parkir (kode_slot, jenis_kendaraan, zona) VALUES (?,?,?)");
        $stmt->bind_param('sss', $kode, $jenis, $zona);
        if ($stmt->execute()) flash('success', "Slot $kode ditambahkan.");
        else flash('error', 'Gagal: ' . $koneksi->error);
    } elseif ($_POST['aksi'] === 'hapus') {
        // Cek tidak ada transaksi aktif
        $cekin = $koneksi->query("SELECT 1 FROM transaksi_parkir WHERE id_slot=$id AND waktu_keluar IS NULL LIMIT 1");
        if ($cekin->num_rows > 0) {
            flash('error', 'Slot masih dipakai kendaraan aktif!');
        } else {
            $koneksi->query("DELETE FROM slot_parkir WHERE id_slot=$id");
            flash('success', 'Slot dihapus.');
        }
    }
    redirect('slot.php');
}

$motor = $koneksi->query("SELECT * FROM slot_parkir WHERE jenis_kendaraan='motor' ORDER BY kode_slot");
$mobil = $koneksi->query("SELECT * FROM slot_parkir WHERE jenis_kendaraan='mobil' ORDER BY kode_slot");

include 'includes/header.php';
?>
<h2>🅿️ Kelola Slot Parkir</h2>

<div class="grid" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <h3>📊 Statistik</h3>
    <?php
    $stat = $koneksi->query("SELECT jenis_kendaraan, status, COUNT(*) c FROM slot_parkir GROUP BY jenis_kendaraan, status");
    $data = ['motor'=>['kosong'=>0,'terisi'=>0,'maintenance'=>0], 'mobil'=>['kosong'=>0,'terisi'=>0,'maintenance'=>0]];
    while ($r = $stat->fetch_assoc()) $data[$r['jenis_kendaraan']][$r['status']] = (int)$r['c'];
    ?>
    <p>🏍️ Motor: <b><?= $data['motor']['kosong'] ?></b> kosong · <b><?= $data['motor']['terisi'] ?></b> terisi · <b><?= $data['motor']['maintenance'] ?></b> maintenance</p>
    <p>🚗 Mobil: <b><?= $data['mobil']['kosong'] ?></b> kosong · <b><?= $data['mobil']['terisi'] ?></b> terisi · <b><?= $data['mobil']['maintenance'] ?></b> maintenance</p>
  </div>
  <div class="card">
    <h3>➕ Tambah Slot Baru</h3>
    <form method="post">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-group"><label>Kode Slot</label><input type="text" name="kode_slot" required placeholder="M-21 / C-11"></div>
      <div class="form-group"><label>Jenis</label><select name="jenis"><option value="motor">Motor</option><option value="mobil">Mobil</option></select></div>
      <div class="form-group"><label>Zona</label><input type="text" name="zona" value="A"></div>
      <button class="btn btn-success" type="submit">Tambah Slot</button>
    </form>
  </div>
</div>

<div class="card">
  <h3>🏍️ Slot Motor</h3>
  <div class="slot-grid">
    <?php while ($r = $motor->fetch_assoc()): 
      $cls = 'slot-' . $r['status'];
    ?>
    <div class="slot-item <?= $cls ?>" 
         onclick="ubahStatus(<?= $r['id_slot'] ?>, '<?= e($r['kode_slot']) ?>', '<?= $r['status'] ?>')"
         style="cursor:pointer;">
      <?= e($r['kode_slot']) ?>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<div class="card">
  <h3>🚗 Slot Mobil</h3>
  <div class="slot-grid">
    <?php while ($r = $mobil->fetch_assoc()): 
      $cls = 'slot-' . $r['status'];
    ?>
    <div class="slot-item <?= $cls ?>" 
         onclick="ubahStatus(<?= $r['id_slot'] ?>, '<?= e($r['kode_slot']) ?>', '<?= $r['status'] ?>')"
         style="cursor:pointer;">
      <?= e($r['kode_slot']) ?>
    </div>
    <?php endwhile; ?>
  </div>
</div>

<form method="post" id="formStatus" style="display:none;">
  <input type="hidden" name="aksi" value="status">
  <input type="hidden" name="id_slot" id="f_id">
  <input type="hidden" name="status" id="f_status">
</form>

<script>
function ubahStatus(id, kode, current) {
  const opts = ['kosong','terisi','maintenance'];
  const next = prompt(`Ubah status slot ${kode}\nSaat ini: ${current}\n\nKetik: kosong / terisi / maintenance`, current);
  if (!next || !opts.includes(next.toLowerCase())) return;
  document.getElementById('f_id').value = id;
  document.getElementById('f_status').value = next.toLowerCase();
  document.getElementById('formStatus').submit();
}
</script>
<?php include 'includes/footer.php'; ?>