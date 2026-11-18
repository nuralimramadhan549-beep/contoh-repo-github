<?php
// ============================================================
// KELOLA TARIF - ADMIN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Tarif Parkir';
$page_subtitle = 'Kelola tarif motor & mobil';
$uid = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['tarif'] as $id => $val) {
        $id = (int)$id;
        $per_jam = max(0, (float)$val['per_jam']);
        $minimal = max(0, (float)$val['minimal']);
        $maksimal = max(0, (float)$val['maksimal']);
        $denda = max(0, (float)$val['denda']);
        $stmt = $koneksi->prepare("UPDATE tarif_parkir SET tarif_per_jam=?, tarif_minimal=?, tarif_maksimal=?, denda_hilang_tiket=? WHERE id_tarif=?");
        $stmt->bind_param('ddddi', $per_jam, $minimal, $maksimal, $denda, $id);
        $stmt->execute();
    }
    log_aktivitas($koneksi, $uid, 'update_tarif', 'Update tarif parkir');
    flash('success', 'Tarif berhasil diperbarui.');
    redirect(PATH_ADMIN . 'tarif.php');
}

include __DIR__ . '/../includes/header.php';
?>

<h2 style="margin-bottom:6px;">💰 Kelola Tarif Parkir</h2>
<p style="color:#64748b;margin-bottom:20px;">Atur tarif per jam, minimal, maksimal, dan denda untuk motor & mobil.</p>

<div class="card">
  <form method="post">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Jenis</th>
            <th>Tarif / Jam</th>
            <th>Tarif Minimal</th>
            <th>Tarif Maksimal</th>
            <th>Denda Hilang Tiket</th>
          </tr>
        </thead>
        <tbody>
          <?php $q = $koneksi->query("SELECT * FROM tarif_parkir"); while ($r = $q->fetch_assoc()): ?>
            <tr>
              <td>
                <b style="font-size:16px;">
                  <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?>
                </b>
              </td>
              <td>
                <input type="number" name="tarif[<?= $r['id_tarif'] ?>][per_jam]" 
                       value="<?= (int)$r['tarif_per_jam'] ?>" step="500" min="0"
                       style="width:130px;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-weight:700;">
              </td>
              <td>
                <input type="number" name="tarif[<?= $r['id_tarif'] ?>][minimal]" 
                       value="<?= (int)$r['tarif_minimal'] ?>" step="500" min="0"
                       style="width:130px;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;">
              </td>
              <td>
                <input type="number" name="tarif[<?= $r['id_tarif'] ?>][maksimal]" 
                       value="<?= (int)$r['tarif_maksimal'] ?>" step="1000" min="0"
                       style="width:130px;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;">
              </td>
              <td>
                <input type="number" name="tarif[<?= $r['id_tarif'] ?>][denda]" 
                       value="<?= (int)$r['denda_hilang_tiket'] ?>" step="1000" min="0"
                       style="width:130px;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;">
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <br>
    <button type="submit" class="btn btn-primary btn-lg">💾 Simpan Perubahan</button>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>