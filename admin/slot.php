<?php
// ============================================================
// KELOLA SLOT - ADMIN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Slot Parkir';
$page_subtitle = 'Kelola slot parkir motor & mobil';
$uid = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    $aksi = $_POST['aksi'];

    if ($aksi === 'tambah') {
        $kode = strtoupper(trim($_POST['kode_slot']));
        $jenis = $_POST['jenis'];
        $zona = strtoupper(trim($_POST['zona'] ?? 'A'));

        $stmt = $koneksi->prepare("INSERT INTO slot_parkir (kode_slot, jenis_kendaraan, zona) VALUES (?,?,?)");
        $stmt->bind_param('sss', $kode, $jenis, $zona);
        if ($stmt->execute()) {
            log_aktivitas($koneksi, $uid, 'tambah_slot', "Kode: $kode");
            flash('success', "Slot <b>$kode</b> ditambahkan.");
        } else {
            flash('error', 'Gagal: ' . $koneksi->error);
        }
    } elseif ($aksi === 'status') {
        $id = (int)$_POST['id_slot'];
        $st = $_POST['status'];
        if (in_array($st, ['kosong','maintenance'])) {
            $koneksi->query("UPDATE slot_parkir SET status='$st' WHERE id_slot=$id");
            flash('success', 'Status slot diubah.');
        }
    } elseif ($aksi === 'hapus') {
        $id = (int)$_POST['id_slot'];
        $cekin = $koneksi->query("SELECT 1 FROM transaksi_parkir WHERE id_slot=$id AND waktu_keluar IS NULL LIMIT 1");
        if ($cekin->num_rows > 0) {
            flash('error', 'Slot sedang dipakai kendaraan aktif!');
        } else {
            $koneksi->query("DELETE FROM slot_parkir WHERE id_slot=$id");
            log_aktivitas($koneksi, $uid, 'hapus_slot', "ID: $id");
            flash('success', 'Slot dihapus.');
        }
    }
    redirect(PATH_ADMIN . 'slot.php');
}

$motor = $koneksi->query("SELECT * FROM slot_parkir WHERE jenis_kendaraan='motor' ORDER BY kode_slot");
$mobil = $koneksi->query("SELECT * FROM slot_parkir WHERE jenis_kendaraan='mobil' ORDER BY kode_slot");

$stat = $koneksi->query("SELECT jenis_kendaraan, status, COUNT(*) c FROM slot_parkir GROUP BY jenis_kendaraan, status");
$data = ['motor'=>['kosong'=>0,'terisi'=>0,'maintenance'=>0], 'mobil'=>['kosong'=>0,'terisi'=>0,'maintenance'=>0]];
while ($r = $stat->fetch_assoc()) $data[$r['jenis_kendaraan']][$r['status']] = (int)$r['c'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2>🅿️ Kelola Slot Parkir</h2>
    <p>Total <b><?= array_sum(array_map('array_sum', $data)) ?></b> slot terdaftar.</p>
  </div>
  <button class="btn btn-success" onclick="toggleForm()">➕ Tambah Slot</button>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><h3>🏍️ Motor</h3></div>
    <div style="display:flex;gap:12px;">
      <div style="flex:1;text-align:center;padding:12px;background:#dcfce7;border-radius:10px;">
        <div style="font-size:22px;font-weight:800;color:#16a34a;"><?= $data['motor']['kosong'] ?></div>
        <div style="font-size:11px;color:#166534;font-weight:700;">KOSONG</div>
      </div>
      <div style="flex:1;text-align:center;padding:12px;background:#fee2e2;border-radius:10px;">
        <div style="font-size:22px;font-weight:800;color:#dc2626;"><?= $data['motor']['terisi'] ?></div>
        <div style="font-size:11px;color:#991b1b;font-weight:700;">TERISI</div>
      </div>
      <div style="flex:1;text-align:center;padding:12px;background:#fef3c7;border-radius:10px;">
        <div style="font-size:22px;font-weight:800;color:#d97706;"><?= $data['motor']['maintenance'] ?></div>
        <div style="font-size:11px;color:#92400e;font-weight:700;">MTN</div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><h3>🚗 Mobil</h3></div>
    <div style="display:flex;gap:12px;">
      <div style="flex:1;text-align:center;padding:12px;background:#dcfce7;border-radius:10px;">
        <div style="font-size:22px;font-weight:800;color:#16a34a;"><?= $data['mobil']['kosong'] ?></div>
        <div style="font-size:11px;color:#166534;font-weight:700;">KOSONG</div>
      </div>
      <div style="flex:1;text-align:center;padding:12px;background:#fee2e2;border-radius:10px;">
        <div style="font-size:22px;font-weight:800;color:#dc2626;"><?= $data['mobil']['terisi'] ?></div>
        <div style="font-size:11px;color:#991b1b;font-weight:700;">TERISI</div>
      </div>
      <div style="flex:1;text-align:center;padding:12px;background:#fef3c7;border-radius:10px;">
        <div style="font-size:22px;font-weight:800;color:#d97706;"><?= $data['mobil']['maintenance'] ?></div>
        <div style="font-size:11px;color:#92400e;font-weight:700;">MTN</div>
      </div>
    </div>
  </div>
</div>

<div class="card" id="formTambah" style="display:none;">
  <div class="card-header"><h3>➕ Tambah Slot Baru</h3></div>
  <form method="post">
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-row">
      <div class="form-group">
        <label>Kode Slot *</label>
        <input type="text" name="kode_slot" required placeholder="M-21 / C-11" style="text-transform:uppercase;">
      </div>
      <div class="form-group">
        <label>Jenis *</label>
        <select name="jenis" required>
          <option value="motor">🏍️ Motor</option>
          <option value="mobil">🚗 Mobil</option>
        </select>
      </div>
      <div class="form-group">
        <label>Zona</label>
        <input type="text" name="zona" value="A" placeholder="A/B/C">
      </div>
    </div>
    <button type="submit" class="btn btn-success">💾 Simpan</button>
    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Batal</button>
  </form>
</div>

<!-- Slot Motor -->
<div class="card">
  <div class="card-header"><h3>🏍️ Slot Motor (<?= $motor->num_rows ?>)</h3></div>
  <div class="slot-grid">
    <?php while ($r = $motor->fetch_assoc()): ?>
      <div class="slot-item slot-<?= $r['status'] ?>" 
           onclick="aksiSlot(<?= $r['id_slot'] ?>, '<?= e($r['kode_slot']) ?>', '<?= $r['status'] ?>')"
           title="Klik untuk ubah status">
        <?= e($r['kode_slot']) ?>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<!-- Slot Mobil -->
<div class="card">
  <div class="card-header"><h3>🚗 Slot Mobil (<?= $mobil->num_rows ?>)</h3></div>
  <div class="slot-grid">
    <?php while ($r = $mobil->fetch_assoc()): ?>
      <div class="slot-item slot-<?= $r['status'] ?>" 
           onclick="aksiSlot(<?= $r['id_slot'] ?>, '<?= e($r['kode_slot']) ?>', '<?= $r['status'] ?>')"
           title="Klik untuk ubah status">
        <?= e($r['kode_slot']) ?>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<!-- Form tersembunyi untuk ubah status -->
<form method="post" id="formStatus" style="display:none;">
  <input type="hidden" name="aksi" value="status">
  <input type="hidden" name="id_slot" id="f_id">
  <input type="hidden" name="status" id="f_status">
</form>

<script>
function toggleForm() {
  const f = document.getElementById('formTambah');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
  if (f.style.display === 'block') f.scrollIntoView({ behavior: 'smooth' });
}
function aksiSlot(id, kode, current) {
  const opt = prompt(`Slot ${kode}\nStatus saat ini: ${current}\n\nKetik: kosong / maintenance`, current);
  if (!opt) return;
  if (!['kosong','maintenance'].includes(opt.toLowerCase())) {
    alert('Hanya boleh: kosong / maintenance');
    return;
  }
  document.getElementById('f_id').value = id;
  document.getElementById('f_status').value = opt.toLowerCase();
  document.getElementById('formStatus').submit();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>