<?php
// ============================================================
// RIWAYAT SEMUA TRANSAKSI - ADMIN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Riwayat Transaksi';
$page_subtitle = 'Semua transaksi parkir';

$filter = $_GET['filter'] ?? 'semua';
$search = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($filter === 'aktif') $where .= " AND t.status='aktif'";
if ($filter === 'selesai') $where .= " AND t.status='selesai'";
if ($filter === 'batal') $where .= " AND t.status='batal'";

if ($search !== '') {
    $where .= " AND (t.plat_nomor LIKE ? OR t.kode_tiket LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s];
    $types = 'ss';
}

$sql = "SELECT t.*, s.kode_slot, s.zona, 
    op.nama_lengkap AS operator_nama,
    pl.nama_lengkap AS pelanggan_nama,
    p.total_biaya, p.denda, p.metode_pembayaran, p.waktu_bayar
    FROM transaksi_parkir t
    JOIN slot_parkir s ON t.id_slot = s.id_slot
    LEFT JOIN users op ON t.operator_id = op.id_user
    LEFT JOIN users pl ON t.user_id = pl.id_user
    LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
    $where
    ORDER BY t.waktu_masuk DESC LIMIT 200";

if (!empty($params)) {
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $data = $stmt->get_result();
} else {
    $data = $koneksi->query($sql);
}

$total = $koneksi->query("SELECT COUNT(*) c FROM transaksi_parkir")->fetch_assoc()['c'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2>📜 Riwayat Transaksi</h2>
    <p>Total <b><?= number_format($total) ?></b> transaksi tercatat (max 200 ditampilkan).</p>
  </div>
</div>

<div class="card">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <div class="form-group" style="flex:1;min-width:200px;margin:0;">
      <label>Cari</label>
      <input type="text" name="q" placeholder="Plat nomor / kode tiket..." value="<?= e($search) ?>">
    </div>
    <div class="form-group" style="margin:0;">
      <label>Filter</label>
      <select name="filter">
        <option value="semua" <?= $filter === 'semua' ? 'selected' : '' ?>>Semua</option>
        <option value="aktif" <?= $filter === 'aktif' ? 'selected' : '' ?>>🅿️ Aktif</option>
        <option value="selesai" <?= $filter === 'selesai' ? 'selected' : '' ?>>✅ Selesai</option>
        <option value="batal" <?= $filter === 'batal' ? 'selected' : '' ?>>❌ Batal</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">🔍 Filter</button>
    <?php if ($search || $filter !== 'semua'): ?>
      <a href="<?= PATH_ADMIN ?>riwayat.php" class="btn btn-secondary">Reset</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Kode</th>
          <th>Plat</th>
          <th>Jenis</th>
          <th>Slot</th>
          <th>Durasi</th>
          <th>Biaya</th>
          <th>Sumber</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($data->num_rows === 0): ?>
          <tr><td colspan="9" class="text-center text-muted" style="padding:32px;">Tidak ada data</td></tr>
        <?php else: while ($r = $data->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= date('d/m/Y', strtotime($r['waktu_masuk'])) ?></div>
              <div style="font-size:11px;color:#94a3b8;"><?= date('H:i', strtotime($r['waktu_masuk'])) ?></div>
            </td>
            <td><code style="background:#f1f5f9;padding:3px 6px;border-radius:4px;font-size:11px;"><?= e($r['kode_tiket']) ?></code></td>
            <td><b><?= e($r['plat_nomor']) ?></b></td>
            <td>
              <span class="badge <?= $r['jenis_kendaraan'] === 'motor' ? 'badge-info' : 'badge-warning' ?>">
                <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️' : '🚗' ?>
              </span>
            </td>
            <td><?= e($r['kode_slot']) ?></td>
            <td>
              <?php if ($r['waktu_keluar']): ?>
                <?= (int)$r['durasi_jam'] ?> jam
              <?php else: ?>
                <span style="color:#f59e0b;">⏳ <?= format_durasi($r['waktu_masuk']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['total_biaya']): ?>
                <b><?= rupiah($r['total_biaya'] + $r['denda']) ?></b>
                <div style="font-size:11px;color:#94a3b8;"><?= $r['metode_pembayaran'] ?></div>
              <?php else: ?>
                <span style="color:#94a3b8;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-gray">
                <?= $r['sumber'] === 'kiosk' ? '🖥️' : ($r['sumber'] === 'pelanggan' ? '👤' : '👨‍🔧') ?>
                <?= e(ucfirst($r['sumber'])) ?>
              </span>
            </td>
            <td>
              <?php if ($r['status'] === 'aktif'): ?>
                <span class="badge badge-warning">🅿️</span>
              <?php elseif ($r['status'] === 'selesai'): ?>
                <span class="badge badge-success">✅</span>
              <?php else: ?>
                <span class="badge badge-gray">❌</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>