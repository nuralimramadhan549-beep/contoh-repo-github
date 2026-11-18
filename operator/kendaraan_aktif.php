<?php
// ============================================================
// KENDARAAN AKTIF - OPERATOR
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_operator();

$page_title = 'Kendaraan Aktif';
$page_subtitle = 'Monitoring kendaraan yang sedang parkir';

// Ambil semua kendaraan aktif
$aktif = $koneksi->query("SELECT * FROM v_transaksi_aktif ORDER BY waktu_masuk DESC");

// Filter
$filter = $_GET['filter'] ?? 'semua';
$where_sql = '';
if ($filter === 'motor') $where_sql = " WHERE jenis_kendaraan='motor'";
if ($filter === 'mobil') $where_sql = " WHERE jenis_kendaraan='mobil'";

$aktif_filtered = $koneksi->query("SELECT * FROM v_transaksi_aktif $where_sql ORDER BY waktu_masuk DESC");

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2>🚗 Kendaraan Aktif</h2>
    <p>Total <b><?= $aktif->num_rows ?></b> kendaraan sedang parkir saat ini.</p>
  </div>
  <a href="<?= PATH_OPERATOR ?>masuk.php" class="btn btn-success">➕ Input Masuk</a>
</div>

<!-- Filter -->
<div class="card">
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="?filter=semua" class="btn btn-sm <?= $filter === 'semua' ? 'btn-primary' : 'btn-secondary' ?>">
      📋 Semua (<?= $aktif->num_rows ?>)
    </a>
    <a href="?filter=motor" class="btn btn-sm <?= $filter === 'motor' ? 'btn-primary' : 'btn-secondary' ?>">
      🏍️ Motor
    </a>
    <a href="?filter=mobil" class="btn btn-sm <?= $filter === 'mobil' ? 'btn-primary' : 'btn-secondary' ?>">
      🚗 Mobil
    </a>
  </div>
</div>

<!-- List -->
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Kode Tiket</th>
          <th>Plat</th>
          <th>Jenis</th>
          <th>Slot</th>
          <th>Masuk</th>
          <th>Durasi</th>
          <th>Sumber</th>
          <th>Pelanggan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($aktif_filtered->num_rows === 0): ?>
          <tr><td colspan="9" class="text-center text-muted" style="padding:32px;">
            🅿️ Belum ada kendaraan parkir<?= $filter !== 'semua' ? " jenis $filter" : '' ?>
          </td></tr>
        <?php else: while ($r = $aktif_filtered->fetch_assoc()): ?>
          <tr>
            <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-size:11.5px;"><?= e($r['kode_tiket']) ?></code></td>
            <td><b><?= e($r['plat_nomor']) ?></b></td>
            <td>
              <span class="badge <?= $r['jenis_kendaraan'] === 'motor' ? 'badge-info' : 'badge-warning' ?>">
                <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️' : '🚗' ?>
                <?= e($r['jenis_kendaraan']) ?>
              </span>
            </td>
            <td><b><?= e($r['kode_slot']) ?></b> <span style="color:#94a3b8;font-size:11px;">(<?= e($r['zona']) ?>)</span></td>
            <td><?= date('H:i', strtotime($r['waktu_masuk'])) ?></td>
            <td><?= format_durasi($r['waktu_masuk']) ?></td>
            <td>
              <?php if ($r['sumber'] === 'kiosk'): ?>
                <span class="badge badge-info">🖥️ Kiosk</span>
              <?php elseif ($r['sumber'] === 'pelanggan'): ?>
                <span class="badge badge-primary">👤 Pelanggan</span>
              <?php else: ?>
                <span class="badge badge-gray">👨‍🔧 Operator</span>
              <?php endif; ?>
            </td>
            <td>
              <?= $r['pelanggan_nama'] ? e($r['pelanggan_nama']) : '<span style="color:#94a3b8;">Walk-in</span>' ?>
            </td>
            <td>
              <a href="<?= PATH_OPERATOR ?>keluar.php?plat=<?= urlencode($r['plat_nomor']) ?>" 
                 class="btn btn-sm btn-warning">💳 Keluar</a>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>