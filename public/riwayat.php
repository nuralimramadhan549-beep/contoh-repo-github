<?php
// ============================================================
// RIWAYAT TRANSAKSI - PELANGGAN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_pelanggan();

$page_title = 'Riwayat Saya';
$page_subtitle = 'Semua transaksi parkir Anda';
$uid = user_id();
$highlight = (int)($_GET['highlight'] ?? 0);

$filter = $_GET['filter'] ?? 'semua';
$where = "WHERE t.user_id = ?";
$params = [$uid];
$types = 'i';

if ($filter === 'aktif') {
    $where .= " AND t.waktu_keluar IS NULL AND t.status IN ('aktif','menunggu_approval')";
} elseif ($filter === 'menunggu') {
    $where .= " AND t.status='menunggu_approval'";
} elseif ($filter === 'selesai') {
    $where .= " AND t.status='selesai'";
}

$sql = "SELECT t.*, s.kode_slot, s.zona, p.total_biaya, p.denda, p.metode_pembayaran, p.waktu_bayar
        FROM transaksi_parkir t
        JOIN slot_parkir s ON t.id_slot = s.id_slot
        LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
        $where
        ORDER BY t.waktu_masuk DESC
        LIMIT 100";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$riwayat = $stmt->get_result();

$stmt = $koneksi->prepare("SELECT COUNT(*) total FROM transaksi_parkir WHERE user_id=?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$total_trx = $stmt->get_result()->fetch_assoc()['total'];

// Jumlah menunggu
$stmt = $koneksi->prepare("SELECT COUNT(*) c FROM transaksi_parkir WHERE user_id=? AND status='menunggu_approval'");
$stmt->bind_param('i', $uid);
$stmt->execute();
$total_waiting = $stmt->get_result()->fetch_assoc()['c'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2>📜 Riwayat Saya</h2>
    <p>Total <b><?= number_format($total_trx) ?></b> transaksi tercatat.</p>
  </div>
</div>

<?php if ($total_waiting > 0): ?>
  <div class="alert alert-warning">
    <span class="alert-icon">⏳</span>
    <div>
      <b><?= $total_waiting ?> transaksi</b> sedang menunggu konfirmasi operator.
      Pembayaran Anda akan segera diproses.
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="?filter=semua" class="btn btn-sm <?= $filter === 'semua' ? 'btn-primary' : 'btn-secondary' ?>">Semua</a>
    <a href="?filter=aktif" class="btn btn-sm <?= $filter === 'aktif' ? 'btn-primary' : 'btn-secondary' ?>">🅿️ Parkir</a>
    <a href="?filter=menunggu" class="btn btn-sm <?= $filter === 'menunggu' ? 'btn-primary' : 'btn-secondary' ?>">
      ⏳ Menunggu <?= $total_waiting > 0 ? "($total_waiting)" : '' ?>
    </a>
    <a href="?filter=selesai" class="btn btn-sm <?= $filter === 'selesai' ? 'btn-primary' : 'btn-secondary' ?>">✅ Selesai</a>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Kode Tiket</th>
          <th>Plat</th>
          <th>Slot</th>
          <th>Durasi</th>
          <th>Biaya</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($riwayat->num_rows === 0): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">
            📭 Belum ada transaksi
          </td></tr>
        <?php else: while ($r = $riwayat->fetch_assoc()): 
          $is_highlight = ($r['id_transaksi'] == $highlight);
        ?>
          <tr style="<?= $is_highlight ? 'background:#fef3c7;' : '' ?>">
            <td>
              <div style="font-weight:600;"><?= date('d/m/Y', strtotime($r['waktu_masuk'])) ?></div>
              <div style="font-size:11px;color:#94a3b8;"><?= date('H:i', strtotime($r['waktu_masuk'])) ?></div>
            </td>
            <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-size:11.5px;"><?= e($r['kode_tiket']) ?></code></td>
            <td><b><?= e($r['plat_nomor']) ?></b></td>
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
              <?php if ($r['status'] === 'aktif'): ?>
                <span class="badge badge-info">🅿️ Parkir</span>
              <?php elseif ($r['status'] === 'menunggu_approval'): ?>
                <span class="badge badge-warning">⏳ Menunggu</span>
              <?php elseif ($r['status'] === 'selesai'): ?>
                <span class="badge badge-success">✅ Selesai</span>
              <?php else: ?>
                <span class="badge badge-gray">Batal</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status'] === 'selesai'): ?>
                <a href="<?= PATH_PUBLIC ?>struk.php?id=<?= $r['id_transaksi'] ?>" class="btn btn-sm btn-secondary">🧾 Struk</a>
              <?php elseif ($r['status'] === 'aktif'): ?>
                <a href="<?= PATH_PUBLIC ?>keluar.php" class="btn btn-sm btn-warning">📤 Ajukan</a>
              <?php elseif ($r['status'] === 'menunggu_approval'): ?>
                <span style="font-size:11px;color:#b45309;font-weight:700;">Tunggu operator</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>