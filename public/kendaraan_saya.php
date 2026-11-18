<?php
// ============================================================
// KENDARAAN SAYA - PELANGGAN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_pelanggan();

$page_title = 'Kendaraan Saya';
$page_subtitle = 'Kelola daftar kendaraan pribadi Anda';
$uid = user_id();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH
    if ($aksi === 'tambah') {
        $plat = validasi_plat($_POST['plat_nomor'] ?? '');
        $jenis = $_POST['jenis_kendaraan'] ?? '';
        $merk = trim($_POST['merk'] ?? '');
        $warna = trim($_POST['warna'] ?? '');
        $errors = [];

        if (!$plat) $errors[] = 'Format plat nomor tidak valid.';
        if (!in_array($jenis, ['motor','mobil'])) $errors[] = 'Pilih jenis kendaraan.';

        // Cek duplikat
        if ($plat && !$errors) {
            $stmt = $koneksi->prepare("SELECT id_kendaraan FROM kendaraan_pelanggan WHERE user_id=? AND plat_nomor=?");
            $stmt->bind_param('is', $uid, $plat);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = "Kendaraan <b>$plat</b> sudah terdaftar.";
            }
        }

        // Cek kendaraan sedang parkir
        if ($plat && !$errors) {
            if (plat_sedang_parkir($koneksi, $plat)) {
                $errors[] = "Kendaraan <b>$plat</b> sedang parkir. Tunggu sampai selesai.";
            }
        }

        if (!$errors) {
            $stmt = $koneksi->prepare("INSERT INTO kendaraan_pelanggan (user_id, plat_nomor, jenis_kendaraan, merk, warna) VALUES (?,?,?,?,?)");
            $stmt->bind_param('issss', $uid, $plat, $jenis, $merk, $warna);
            if ($stmt->execute()) {
                log_aktivitas($koneksi, $uid, 'tambah_kendaraan', "Plat: $plat");
                flash('success', "Kendaraan $plat berhasil ditambahkan.");
            } else {
                flash('error', 'Gagal menyimpan: ' . $koneksi->error);
            }
            redirect(PATH_PUBLIC . 'kendaraan_saya.php');
        } else {
            flash('error', implode('<br>', $errors));
        }
    }

    // HAPUS
    if ($aksi === 'hapus') {
        $id = (int)($_POST['id_kendaraan'] ?? 0);
        // Cek milik sendiri
        $stmt = $koneksi->prepare("SELECT plat_nomor FROM kendaraan_pelanggan WHERE id_kendaraan=? AND user_id=?");
        $stmt->bind_param('ii', $id, $uid);
        $stmt->execute();
        $kend = $stmt->get_result()->fetch_assoc();

        if (!$kend) {
            flash('error', 'Kendaraan tidak ditemukan.');
        } elseif (plat_sedang_parkir($koneksi, $kend['plat_nomor'])) {
            flash('error', 'Kendaraan sedang parkir, tidak bisa dihapus.');
        } else {
            $stmt = $koneksi->prepare("DELETE FROM kendaraan_pelanggan WHERE id_kendaraan=? AND user_id=?");
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            log_aktivitas($koneksi, $uid, 'hapus_kendaraan', "Plat: {$kend['plat_nomor']}");
            flash('success', 'Kendaraan berhasil dihapus.');
        }
        redirect(PATH_PUBLIC . 'kendaraan_saya.php');
    }

    // SET DEFAULT
    if ($aksi === 'set_default') {
        $id = (int)($_POST['id_kendaraan'] ?? 0);
        $koneksi->query("UPDATE kendaraan_pelanggan SET is_default=0 WHERE user_id=$uid");
        $stmt = $koneksi->prepare("UPDATE kendaraan_pelanggan SET is_default=1 WHERE id_kendaraan=? AND user_id=?");
        $stmt->bind_param('ii', $id, $uid);
        $stmt->execute();
        flash('success', 'Kendaraan default diperbarui.');
        redirect(PATH_PUBLIC . 'kendaraan_saya.php');
    }
}

// Ambil daftar kendaraan
$stmt = $koneksi->prepare("SELECT * FROM kendaraan_pelanggan WHERE user_id=? ORDER BY is_default DESC, created_at DESC");
$stmt->bind_param('i', $uid);
$stmt->execute();
$kendaraan = $stmt->get_result();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2>🚗 Kendaraan Saya</h2>
    <p>Kelola daftar kendaraan pribadi Anda di sini.</p>
  </div>
  <button class="btn btn-success" onclick="toggleForm()">➕ Tambah Kendaraan</button>
</div>

<!-- Form Tambah -->
<div class="card" id="formTambah" style="display:none;">
  <div class="card-header"><h3>➕ Tambah Kendaraan Baru</h3></div>
  <form method="post">
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-row">
      <div class="form-group">
        <label>🚘 Plat Nomor <span style="color:#dc2626;">*</span></label>
        <input type="text" name="plat_nomor" required placeholder="B 1234 XYZ"
               style="text-transform:uppercase;font-weight:700;letter-spacing:2px;">
      </div>
      <div class="form-group">
        <label>🚙 Jenis Kendaraan <span style="color:#dc2626;">*</span></label>
        <select name="jenis_kendaraan" required>
          <option value="">-- Pilih --</option>
          <option value="motor">🏍️ Motor</option>
          <option value="mobil">🚗 Mobil</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>🏷️ Merk (opsional)</label>
        <input type="text" name="merk" placeholder="Honda, Toyota, dll">
      </div>
      <div class="form-group">
        <label>🎨 Warna (opsional)</label>
        <input type="text" name="warna" placeholder="Hitam, Putih, dll">
      </div>
    </div>
    <button type="submit" class="btn btn-success">💾 Simpan</button>
    <button type="button" class="btn btn-secondary" onclick="toggleForm()">Batal</button>
  </form>
</div>

<!-- List Kendaraan -->
<?php if ($kendaraan->num_rows === 0): ?>
  <div class="card text-center" style="padding:60px 24px;">
    <div style="font-size:64px;margin-bottom:16px;">🚗</div>
    <h3 style="color:#64748b;margin-bottom:8px;">Belum ada kendaraan</h3>
    <p style="color:#94a3b8;margin-bottom:20px;">Tambahkan kendaraan Anda untuk parkir lebih cepat.</p>
    <button class="btn btn-success" onclick="toggleForm()">➕ Tambah Kendaraan Pertama</button>
  </div>
<?php else: ?>
  <div class="grid-2">
    <?php while ($k = $kendaraan->fetch_assoc()): ?>
      <div class="card" style="border-left:5px solid <?= $k['jenis_kendaraan'] === 'motor' ? '#0891b2' : '#f59e0b' ?>;position:relative;">
        <?php if ($k['is_default']): ?>
          <span class="badge badge-success" style="position:absolute;top:16px;right:16px;">⭐ DEFAULT</span>
        <?php endif; ?>
        <div style="font-size:40px;margin-bottom:8px;">
          <?= $k['jenis_kendaraan'] === 'motor' ? '🏍️' : '🚗' ?>
        </div>
        <h3 style="font-size:22px;font-weight:800;letter-spacing:2px;margin-bottom:4px;color:#0f172a;">
          <?= e($k['plat_nomor']) ?>
        </h3>
        <div style="color:#64748b;font-size:13px;margin-bottom:16px;">
          <?= $k['jenis_kendaraan'] === 'motor' ? 'Motor' : 'Mobil' ?>
          <?= $k['merk'] ? ' · ' . e($k['merk']) : '' ?>
          <?= $k['warna'] ? ' · ' . e($k['warna']) : '' ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid #f1f5f9;padding-top:14px;">
          <?php if (!$k['is_default']): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="aksi" value="set_default">
              <input type="hidden" name="id_kendaraan" value="<?= $k['id_kendaraan'] ?>">
              <button class="btn btn-sm btn-secondary">⭐ Jadikan Default</button>
            </form>
          <?php endif; ?>
          <a href="<?= PATH_PUBLIC ?>masuk.php?plat=<?= urlencode($k['plat_nomor']) ?>" class="btn btn-sm btn-success">
            🅿️ Parkir Sekarang
          </a>
          <form method="post" style="display:inline" onsubmit="return confirm('Hapus kendaraan ini?')">
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id_kendaraan" value="<?= $k['id_kendaraan'] ?>">
            <button class="btn btn-sm btn-danger">🗑️ Hapus</button>
          </form>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php endif; ?>

<script>
function toggleForm() {
  const f = document.getElementById('formTambah');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
  if (f.style.display === 'block') {
    f.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>