<?php
require_once 'includes/auth.php';
cek_admin();

$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

$stmt = $koneksi->prepare("SELECT DATE(waktu_bayar) tgl, COUNT(*) jml, 
    SUM(CASE WHEN metode_pembayaran='tunai' THEN total_biaya+denda ELSE 0 END) tunai,
    SUM(CASE WHEN metode_pembayaran<>'tunai' THEN total_biaya+denda ELSE 0 END) non_tunai,
    SUM(total_biaya+denda) total 
    FROM pembayaran WHERE DATE(waktu_bayar) BETWEEN ? AND ? 
    GROUP BY DATE(waktu_bayar) ORDER BY tgl DESC");
$stmt->bind_param('ss', $dari, $sampai);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$grand = ['jml'=>0, 'tunai'=>0, 'non_tunai'=>0, 'total'=>0];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $grand['jml'] += $r['jml'];
    $grand['tunai'] += $r['tunai'];
    $grand['non_tunai'] += $r['non_tunai'];
    $grand['total'] += $r['total'];
}

// Export CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan_parkir_' . $dari . '_' . $sampai . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Tanggal','Jumlah Transaksi','Tunai','Non-Tunai','Total']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['tgl'], $r['jml'], $r['tunai'], $r['non_tunai'], $r['total']]);
    }
    fputcsv($out, ['TOTAL', $grand['jml'], $grand['tunai'], $grand['non_tunai'], $grand['total']]);
    fclose($out);
    exit;
}

include 'includes/header.php';
?>
<div class="flex-between mb-2">
  <h2>📈 Laporan Pendapatan</h2>
  <a href="?dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>&export=1" class="btn btn-success">⬇️ Export CSV</a>
</div>

<div class="card">
  <form method="get" class="grid" style="grid-template-columns:1fr 1fr auto;">
    <div class="form-group"><label>Dari</label><input type="date" name="dari" value="<?= e($dari) ?>"></div>
    <div class="form-group"><label>Sampai</label><input type="date" name="sampai" value="<?= e($sampai) ?>"></div>
    <div class="form-group"><label>&nbsp;</label><button class="btn btn-primary">🔍 Tampilkan</button></div>
  </form>
</div>

<div class="grid">
  <div class="stat"><h3>Total Transaksi</h3><p><?= (int)$grand['jml'] ?></p></div>
  <div class="stat success"><h3>Total Pendapatan</h3><p><?= rupiah($grand['total']) ?></p></div>
  <div class="stat"><h3>Tunai</h3><p><?= rupiah($grand['tunai']) ?></p></div>
  <div class="stat warning"><h3>Non-Tunai</h3><p><?= rupiah($grand['non_tunai']) ?></p></div>
</div>

<div class="card mt-3">
  <h3>Detail Harian</h3>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Tanggal</th><th>Transaksi</th><th>Tunai</th><th>Non-Tunai</th><th>Total</th></tr></thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="5" class="text-center" style="padding:24px;color:#64748b;">Belum ada data pada periode ini.</td></tr>
        <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= date('d/m/Y', strtotime($r['tgl'])) ?></td>
          <td><?= (int)$r['jml'] ?></td>
          <td><?= rupiah($r['tunai']) ?></td>
          <td><?= rupiah($r['non_tunai']) ?></td>
          <td><b><?= rupiah($r['total']) ?></b></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:#f1f5f9;font-weight:800;">
          <td colspan="2">TOTAL</td>
          <td><?= rupiah($grand['tunai']) ?></td>
          <td><?= rupiah($grand['non_tunai']) ?></td>
          <td><?= rupiah($grand['total']) ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?>