<?php
require_once __DIR__ . '/includes/auth.php';
cek_login();

$page_title = 'Dashboard';
$page_subtitle = 'Ringkasan operasional parkir hari ini';

$ring = $koneksi->query("SELECT * FROM v_ringkasan_hari_ini")->fetch_assoc();

$slot_motor = $koneksi->query("SELECT 
    COUNT(*) total,
    SUM(CASE WHEN status='kosong' THEN 1 ELSE 0 END) kosong,
    SUM(CASE WHEN status='terisi' THEN 1 ELSE 0 END) terisi
    FROM slot_parkir WHERE jenis_kendaraan='motor'")->fetch_assoc();
$slot_mobil = $koneksi->query("SELECT 
    COUNT(*) total,
    SUM(CASE WHEN status='kosong' THEN 1 ELSE 0 END) kosong,
    SUM(CASE WHEN status='terisi' THEN 1 ELSE 0 END) terisi
    FROM slot_parkir WHERE jenis_kendaraan='mobil'")->fetch_assoc();

$chartData = [];
$q = $koneksi->query("SELECT DATE(waktu_bayar) tgl, SUM(total_biaya + denda) total 
    FROM pembayaran 
    WHERE waktu_bayar >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(waktu_bayar) ORDER BY tgl");
while ($r = $q->fetch_assoc()) $chartData[$r['tgl']] = (float)$r['total'];

$labels = [];
$values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('d/m', strtotime($d));
    $values[] = $chartData[$d] ?? 0;
}

$aktif = $koneksi->query("SELECT * FROM v_transaksi_aktif ORDER BY waktu_masuk DESC LIMIT 6");

include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
    </div>
    <div class="stat-label">Kendaraan Masuk Hari Ini</div>
    <div class="stat-value"><?= number_format($ring['masuk_hari_ini']) ?></div>
    <div class="stat-desc">Total yang tercatat hari ini</div>
  </div>

  <div class="stat-card orange">
    <div class="stat-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 17h14M5 17a2 2 0 1 0 4 0M15 17a2 2 0 1 0 4 0"/>
        <path d="M3 13l2-6a2 2 0 0 1 2-1.5h10A2 2 0 0 1 19 7l2 6v4h-2"/>
        <path d="M3 13h18"/>
      </svg>
    </div>
    <div class="stat-label">Masih Parkir</div>
    <div class="stat-value"><?= number_format($ring['masih_parkir']) ?></div>
    <div class="stat-desc">Kendaraan aktif di area</div>
  </div>

  <div class="stat-card green">
    <div class="stat-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="1" x2="12" y2="23"/>
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
      </svg>
    </div>
    <div class="stat-label">Pendapatan Hari Ini</div>
    <div class="stat-value" style="font-size:24px;"><?= rupiah($ring['pendapatan_hari_ini']) ?></div>
    <div class="stat-desc">Termasuk denda</div>
  </div>

  <div class="stat-card cyan">
    <div class="stat-icon">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <text x="12" y="17" text-anchor="middle" font-size="12" font-weight="bold" fill="#fff" stroke="none">P</text>
      </svg>
    </div>
    <div class="stat-label">Slot Kosong</div>
    <div class="stat-value"><?= number_format($ring['slot_kosong']) ?> <span style="font-size:16px;color:#94a3b8;font-weight:600;">/ <?= number_format($ring['slot_total']) ?></span></div>
    <div class="stat-desc">Ketersediaan slot</div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3>⚡ Aksi Cepat</h3>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="kendaraan_masuk.php" class="btn btn-success">🚗 Kendaraan Masuk</a>
    <a href="kendaraan_keluar.php" class="btn btn-warning">🚙 Kendaraan Keluar</a>
    <a href="kiosk.php" target="_blank" class="btn btn-primary">🖥️ Buka Kiosk</a>
    <?php if (is_admin()): ?>
      <a href="laporan.php" class="btn btn-secondary">📈 Lihat Laporan</a>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h3>🏍️ Slot Motor</h3>
      <span class="badge badge-info"><?= $slot_motor['kosong'] ?> / <?= $slot_motor['total'] ?> kosong</span>
    </div>
    <div style="display:flex;gap:16px;margin-bottom:16px;">
      <div style="flex:1;text-align:center;padding:14px;background:#f0fdf4;border-radius:10px;">
        <div style="font-size:24px;font-weight:800;color:#16a34a;"><?= $slot_motor['kosong'] ?></div>
        <div style="font-size:12px;color:#166534;font-weight:600;">KOSONG</div>
      </div>
      <div style="flex:1;text-align:center;padding:14px;background:#fef2f2;border-radius:10px;">
        <div style="font-size:24px;font-weight:800;color:#dc2626;"><?= $slot_motor['terisi'] ?></div>
        <div style="font-size:12px;color:#991b1b;font-weight:600;">TERISI</div>
      </div>
    </div>
    <div style="height:8px;background:#e2e8f0;border-radius:8px;overflow:hidden;">
      <div style="height:100%;background:linear-gradient(90deg,#22c55e,#16a34a);width:<?= $slot_motor['total'] > 0 ? ($slot_motor['terisi']/$slot_motor['total']*100) : 0 ?>%;transition:width .5s;"></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3>🚗 Slot Mobil</h3>
      <span class="badge badge-info"><?= $slot_mobil['kosong'] ?> / <?= $slot_mobil['total'] ?> kosong</span>
    </div>
    <div style="display:flex;gap:16px;margin-bottom:16px;">
      <div style="flex:1;text-align:center;padding:14px;background:#f0fdf4;border-radius:10px;">
        <div style="font-size:24px;font-weight:800;color:#16a34a;"><?= $slot_mobil['kosong'] ?></div>
        <div style="font-size:12px;color:#166534;font-weight:600;">KOSONG</div>
      </div>
      <div style="flex:1;text-align:center;padding:14px;background:#fef2f2;border-radius:10px;">
        <div style="font-size:24px;font-weight:800;color:#dc2626;"><?= $slot_mobil['terisi'] ?></div>
        <div style="font-size:12px;color:#991b1b;font-weight:600;">TERISI</div>
      </div>
    </div>
    <div style="height:8px;background:#e2e8f0;border-radius:8px;overflow:hidden;">
      <div style="height:100%;background:linear-gradient(90deg,#22c55e,#16a34a);width:<?= $slot_mobil['total'] > 0 ? ($slot_mobil['terisi']/$slot_mobil['total']*100) : 0 ?>%;transition:width .5s;"></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3>📈 Pendapatan 7 Hari Terakhir</h3>
  </div>
  <canvas id="chartPendapatan" height="70"></canvas>
</div>

<div class="card">
  <div class="card-header">
    <h3>🚙 Kendaraan Sedang Parkir</h3>
    <a href="kendaraan_keluar.php" class="btn btn-sm btn-primary">Kelola →</a>
  </div>
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
          <th>Operator</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($aktif->num_rows === 0): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">🅿️ Belum ada kendaraan parkir aktif</td></tr>
        <?php else: while ($r = $aktif->fetch_assoc()):
          $detik = time() - strtotime($r['waktu_masuk']);
          $jam = floor($detik / 3600);
          $menit = floor(($detik % 3600) / 60);
        ?>
          <tr>
            <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-size:12px;"><?= e($r['kode_tiket']) ?></code></td>
            <td><b><?= e($r['plat_nomor']) ?></b></td>
            <td>
              <span class="badge <?= $r['jenis_kendaraan'] === 'motor' ? 'badge-info' : 'badge-warning' ?>">
                <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️' : '🚗' ?> <?= e($r['jenis_kendaraan']) ?>
              </span>
            </td>
            <td><?= e($r['kode_slot']) ?></td>
            <td><?= date('H:i', strtotime($r['waktu_masuk'])) ?></td>
            <td><?= $jam ?>j <?= $menit ?>m</td>
            <td><?= e($r['operator'] ?? ($r['via_kiosk'] ? 'Kiosk' : '-')) ?></td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('chartPendapatan'), {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [{
      label: 'Pendapatan',
      data: <?= json_encode($values) ?>,
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59,130,246,.1)',
      fill: true,
      tension: .4,
      borderWidth: 3,
      pointRadius: 5,
      pointBackgroundColor: '#fff',
      pointBorderColor: '#3b82f6',
      pointBorderWidth: 3
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0f172a',
        padding: 12,
        cornerRadius: 8,
        callbacks: { label: ctx => 'Rp ' + ctx.parsed.y.toLocaleString('id-ID') }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#f1f5f9' },
        ticks: { color: '#94a3b8', font: { size: 11, weight: '600' }, callback: v => 'Rp ' + v.toLocaleString('id-ID') }
      },
      x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11, weight: '600' } } }
    }
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>