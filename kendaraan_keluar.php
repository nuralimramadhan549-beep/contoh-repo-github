<?php
require_once 'includes/auth.php';
cek_login();

$errors = [];
$cari = null;

// Step 1: Cari kendaraan
if (isset($_POST['cari'])) {
    $plat = validasi_plat($_POST['plat_nomor'] ?? '');
    if (!$plat) {
        $errors[] = 'Format plat tidak valid.';
    } else {
        $cari = plat_sedang_parkir($koneksi, $plat);
        if (!$cari) $errors[] = "Kendaraan <b>$plat</b> tidak ditemukan / sudah keluar.";
    }
}

// Step 2: Proses bayar
if (isset($_POST['bayar'])) {
    $id_trx = (int)$_POST['id_transaksi'];
    $hilang = !empty($_POST['hilang_tiket']);
    $metode = $_POST['metode'] ?? 'tunai';
    $bayar = (float)($_POST['bayar'] ?? 0);

    $stmt = $koneksi->prepare("SELECT t.*, s.kode_slot FROM transaksi_parkir t 
        JOIN slot_parkir s ON t.id_slot=s.id_slot 
        WHERE t.id_transaksi=? AND t.waktu_keluar IS NULL AND t.status='aktif' LIMIT 1");
    $stmt->bind_param('i', $id_trx);
    $stmt->execute();
    $trx = $stmt->get_result()->fetch_assoc();

    if (!$trx) {
        $errors[] = 'Transaksi tidak ditemukan atau sudah selesai.';
    } else {
        $hitung = hitung_biaya($koneksi, $trx['jenis_kendaraan'], $trx['waktu_masuk'], null, $hilang);
        $total = $hitung['total'];

        if ($metode === 'tunai' && $bayar < $total) {
            $errors[] = 'Uang bayar kurang dari total biaya.';
        } else {
            $koneksi->begin_transaction();
            try {
                $stmt = $koneksi->prepare("UPDATE transaksi_parkir SET waktu_keluar=NOW(), durasi_jam=?, status='selesai' WHERE id_transaksi=?");
                $stmt->bind_param('ii', $hitung['jam'], $id_trx);
                $stmt->execute();

                $koneksi->query("UPDATE slot_parkir SET status='kosong' WHERE id_slot={$trx['id_slot']}");

                $kembalian = $metode === 'tunai' ? max(0, $bayar - $total) : 0;
                $kasir = user_id();

                $stmt = $koneksi->prepare("INSERT INTO pembayaran 
                    (id_transaksi, total_biaya, denda, metode_pembayaran, bayar, kembalian, kasir_id) 
                    VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('iddsddi', $id_trx, $hitung['biaya'], $hitung['denda'], $metode, $bayar, $kembalian, $kasir);
                $stmt->execute();

                log_aktivitas($koneksi, $kasir, 'kendaraan_keluar', "Plat: {$trx['plat_nomor']}, Total: $total");

                $koneksi->commit();
                flash('success', "Pembayaran berhasil. Total: " . rupiah($total) . ", Kembalian: " . rupiah($kembalian));
                redirect('struk.php?id=' . $id_trx . '&tipe=keluar');
            } catch (Exception $ex) {
                $koneksi->rollback();
                $errors[] = 'Gagal memproses: ' . $ex->getMessage();
            }
        }
    }
}

// Daftar kendaraan aktif
$aktif = $koneksi->query("SELECT * FROM v_transaksi_aktif ORDER BY waktu_masuk ASC");

include 'includes/header.php';
?>
<h2>🚗 Kendaraan Keluar</h2>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <?php foreach ($errors as $er): ?><div>• <?= $er ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h3>🔍 Cari Kendaraan</h3>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="text" name="plat_nomor" required placeholder="Masukkan plat nomor..."
           value="<?= e($_POST['plat_nomor'] ?? '') ?>"
           style="flex:1;min-width:220px;padding:12px;border:1.5px solid #e2e8f0;border-radius:8px;text-transform:uppercase;font-weight:700;font-size:16px;letter-spacing:2px;">
    <button class="btn btn-warning" name="cari" value="1">🔍 Cari</button>
  </form>
</div>

<?php if ($cari):
  $h = hitung_biaya($koneksi, $cari['jenis_kendaraan'], $cari['waktu_masuk']);
?>
<div class="card">
  <h3>💳 Konfirmasi Pembayaran</h3>
  <table style="margin-bottom:20px;">
    <tr><td><b>Kode Tiket</b></td><td><?= e($cari['kode_tiket']) ?></td></tr>
    <tr><td><b>Plat Nomor</b></td><td><b style="font-size:18px;"><?= e($cari['plat_nomor']) ?></b></td></tr>
    <tr><td><b>Jenis</b></td><td><?= e($cari['jenis_kendaraan']) ?></td></tr>
    <tr><td><b>Slot</b></td><td><?= e($cari['kode_slot']) ?></td></tr>
    <tr><td><b>Waktu Masuk</b></td><td><?= date('d/m/Y H:i:s', strtotime($cari['waktu_masuk'])) ?></td></tr>
    <tr><td><b>Durasi</b></td><td><?= $h['jam'] ?> jam</td></tr>
    <tr><td><b>Tarif / Jam</b></td><td><?= rupiah($h['tarif_per_jam']) ?></td></tr>
    <tr style="background:#fef3c7;"><td><b>Biaya Parkir</b></td><td><b><?= rupiah($h['biaya']) ?></b></td></tr>
  </table>

  <form method="post" onsubmit="return validasiBayar()">
    <input type="hidden" name="id_transaksi" value="<?= (int)$cari['id_transaksi'] ?>">
    <input type="hidden" name="plat_nomor" value="<?= e($cari['plat_nomor']) ?>">

    <div class="form-group">
      <label><input type="checkbox" name="hilang_tiket" id="hilang" value="1" onchange="updateTotal()"> 
      Tiket hilang (denda <?= rupiah($h['jam'] > 0 ? 0 : 0) ?><?php 
        $dt = $koneksi->query("SELECT denda_hilang_tiket FROM tarif_parkir WHERE jenis_kendaraan='{$cari['jenis_kendaraan']}'")->fetch_assoc();
        echo rupiah($dt['denda_hilang_tiket']);
      ?>)</label>
    </div>

    <div class="form-group">
      <label>💳 Metode Pembayaran</label>
      <select name="metode" id="metode" onchange="toggleBayar()">
        <option value="tunai">💵 Tunai</option>
        <option value="qris">📱 QRIS</option>
        <option value="e-wallet">📲 E-Wallet</option>
      </select>
    </div>

    <div class="form-group" id="grup-bayar">
      <label>💵 Uang Bayar</label>
      <input type="number" name="bayar" id="bayar" value="0" min="0" step="1000" oninput="updateTotal()">
      <small style="color:#64748b;">Kembalian: <b id="kembalian">Rp 0</b></small>
    </div>

    <div style="background:#dbeafe;padding:16px;border-radius:8px;margin-bottom:16px;">
      <div style="font-size:13px;color:#1e40af;">TOTAL BAYAR</div>
      <div id="total" style="font-size:28px;font-weight:800;color:#1e40af;"><?= rupiah($h['total']) ?></div>
    </div>

    <button type="submit" name="bayar" value="1" class="btn btn-success btn-lg">✅ Proses Bayar & Keluar</button>
    <a href="kendaraan_keluar.php" class="btn btn-secondary btn-lg">Batal</a>
  </form>
</div>

<script>
const BIAYA = <?= (float)$h['biaya'] ?>;
const DENDA = <?= (float)$h['denda'] ?>;
function updateTotal() {
  const hilang = document.getElementById('hilang').checked;
  const denda = hilang ? DENDA : 0;
  const total = BIAYA + denda;
  document.getElementById('total').textContent = 'Rp ' + total.toLocaleString('id-ID');
  const bayar = parseFloat(document.getElementById('bayar').value) || 0;
  const kembali = Math.max(0, bayar - total);
  document.getElementById('kembalian').textContent = 'Rp ' + kembali.toLocaleString('id-ID');
}
function toggleBayar() {
  const m = document.getElementById('metode').value;
  document.getElementById('grup-bayar').style.display = (m === 'tunai') ? 'block' : 'none';
}
function validasiBayar() {
  const m = document.getElementById('metode').value;
  if (m === 'tunai') {
    const bayar = parseFloat(document.getElementById('bayar').value) || 0;
    const hilang = document.getElementById('hilang').checked;
    const total = BIAYA + (hilang ? DENDA : 0);
    if (bayar < total) { alert('Uang bayar kurang!'); return false; }
  }
  return true;
}
</script>
<?php endif; ?>

<div class="card">
  <h3>🚙 Kendaraan Masih Parkir (<?= $aktif->num_rows ?>)</h3>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Kode</th><th>Plat</th><th>Jenis</th><th>Slot</th><th>Masuk</th><th>Durasi</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if ($aktif->num_rows === 0): ?>
          <tr><td colspan="7" class="text-center" style="color:#64748b;padding:24px;">Tidak ada kendaraan parkir.</td></tr>
        <?php else: while ($r = $aktif->fetch_assoc()):
          $detik = time() - strtotime($r['waktu_masuk']);
          $jam = floor($detik/3600); $menit = floor(($detik%3600)/60);
        ?>
        <tr>
          <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:4px;"><?= e($r['kode_tiket']) ?></code></td>
          <td><b><?= e($r['plat_nomor']) ?></b></td>
          <td><span class="badge badge-info"><?= e($r['jenis_kendaraan']) ?></span></td>
          <td><?= e($r['kode_slot']) ?></td>
          <td><?= date('d/m H:i', strtotime($r['waktu_masuk'])) ?></td>
          <td><?= $jam ?>j <?= $menit ?>m</td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="plat_nomor" value="<?= e($r['plat_nomor']) ?>">
              <button class="btn btn-warning btn-sm" name="cari" value="1">Bayar</button>
            </form>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?>