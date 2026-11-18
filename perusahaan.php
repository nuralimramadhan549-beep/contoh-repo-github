<?php
require_once 'includes/auth.php';
cek_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_perusahaan']);
    $alamat = trim($_POST['alamat']);
    $telp = trim($_POST['telepon']);
    $email = trim($_POST['email']);
    $npwp = trim($_POST['npwp']);
    $jam = trim($_POST['jam_operasional']);

    $stmt = $koneksi->prepare("UPDATE perusahaan SET nama_perusahaan=?, alamat=?, telepon=?, email=?, npwp=?, jam_operasional=? WHERE id_perusahaan=1");
    $stmt->bind_param('ssssss', $nama, $alamat, $telp, $email, $npwp, $jam);
    if ($stmt->execute()) {
        unset($_SESSION['perusahaan_cache']);
        flash('success', 'Profil perusahaan diperbarui.');
    } else {
        flash('error', 'Gagal: ' . $koneksi->error);
    }
    redirect('perusahaan.php');
}

$data = $koneksi->query("SELECT * FROM perusahaan LIMIT 1")->fetch_assoc();
include 'includes/header.php';
?>
<h2>🏢 Profil Perusahaan</h2>
<div class="card">
  <form method="post">
    <div class="grid" style="grid-template-columns:1fr 1fr;">
      <div class="form-group"><label>Nama Perusahaan</label><input type="text" name="nama_perusahaan" value="<?= e($data['nama_perusahaan']) ?>" required></div>
      <div class="form-group"><label>Telepon</label><input type="text" name="telepon" value="<?= e($data['telepon']) ?>"></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= e($data['email']) ?>"></div>
      <div class="form-group"><label>NPWP</label><input type="text" name="npwp" value="<?= e($data['npwp']) ?>"></div>
      <div class="form-group"><label>Jam Operasional</label><input type="text" name="jam_operasional" value="<?= e($data['jam_operasional']) ?>"></div>
    </div>
    <div class="form-group"><label>Alamat</label><textarea name="alamat" rows="3"><?= e($data['alamat']) ?></textarea></div>
    <button class="btn btn-primary" type="submit">💾 Simpan</button>
  </form>
</div>
<?php include 'includes/footer.php'; ?>