<?php
// ============================================================
// LAPORAN PENDAPATAN - ADMIN (Custom Date Range)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Laporan Pendapatan';
$page_subtitle = 'Analisis pendapatan dengan filter custom';

// ============================================================
// AMBIL FILTER TANGGAL
// ============================================================
$preset = $_GET['preset'] ?? 'bulan_ini';
$dari = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';

// Set default berdasarkan preset
switch ($preset) {
    case 'hari_ini':
        $dari = $sampai = date('Y-m-d');
        break;
    case '7_hari':
        $dari = date('Y-m-d', strtotime('-6 days'));
        $sampai = date('Y-m-d');
        break;
    case '30_hari':
        $dari = date('Y-m-d', strtotime('-29 days'));
        $sampai = date('Y-m-d');
        break;
    case 'bulan_ini':
        $dari = date('Y-m-01');
        $sampai = date('Y-m-t');
        break;
    case 'bulan_lalu':
        $dari = date('Y-m-01', strtotime('first day of last month'));
        $sampai = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'tahun_ini':
        $dari = date('Y-01-01');
        $sampai = date('Y-12-31');
        break;
    case 'custom':
        // Pakai $dari & $sampai dari GET
        if (!$dari) $dari = date('Y-m-01');
        if (!$sampai) $sampai = date('Y-m-d');
        break;
    default:
        $dari = date('Y-m-01');
        $sampai = date('Y-m-d');
}

// Validasi
if ($dari > $sampai) {
    $tmp = $dari;
    $dari = $sampai;
    $sampai = $tmp;
}

// ============================================================
// EXPORT CSV
// ============================================================
if (isset($_GET['export'])) {
    $stmt = $koneksi->prepare("SELECT 
        DATE(p.waktu_bayar) AS tgl,
        COUNT(*) AS jumlah_transaksi,
        SUM(CASE WHEN p.metode_pembayaran='tunai' THEN p.total_biaya + p.denda ELSE 0 END) AS tunai,
        SUM(CASE WHEN p.metode_pembayaran<>'tunai' THEN p.total_biaya + p.denda ELSE 0 END) AS non_tunai,
        SUM(p.total_biaya + p.denda) AS total
        FROM pembayaran p
        WHERE DATE(p.waktu_bayar) BETWEEN ? AND ?
        GROUP BY DATE(p.waktu_bayar)
        ORDER BY tgl ASC");
    $stmt->bind_param('ss', $dari, $sampai);
    $stmt->execute();
    $data = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan_' . $dari . '_sd_' . $sampai . '.csv');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    // Header info
    fputcsv($out, ['LAPORAN PENDAPATAN PARKIR']);
    fputcsv($out, ['PT. Parkir Bogor']);
    fputcsv($out, ['Periode', $dari . ' s/d ' . $sampai]);
    fputcsv($out, []);
    fputcsv($out, ['Tanggal', 'Jumlah Transaksi', 'Tunai (Rp)', 'Non-Tunai (Rp)', 'Total (Rp)']);

    $grand = ['jml'=>0, 'tunai'=>0, 'non_tunai'=>0, 'total'=>0];
    while ($r = $data->fetch_assoc()) {
        fputcsv($out, [
            $r['tgl'],
            $r['jumlah_transaksi'],
            $r['tunai'],
            $r['non_tunai'],
            $r['total']
        ]);
        $grand['jml'] += $r['jumlah_transaksi'];
        $grand['tunai'] += $r['tunai'];
        $grand['non_tunai'] += $r['non_tunai'];
        $grand['total'] += $r['total'];
    }
    fputcsv($out, ['TOTAL', $grand['jml'], $grand['tunai'], $grand['non_tunai'], $grand['total']]);
    fclose($out);
    exit;
}

// ============================================================
// DATA UNTUK TAMPILAN
// ============================================================

// 1. Detail harian
$stmt = $koneksi->prepare("SELECT 
    DATE(p.waktu_bayar) AS tgl,
    COUNT(*) AS jml,
    SUM(CASE WHEN p.metode_pembayaran='tunai' THEN p.total_biaya + p.denda ELSE 0 END) AS tunai,
    SUM(CASE WHEN p.metode_pembayaran<>'tunai' THEN p.total_biaya + p.denda ELSE 0 END) AS non_tunai,
    SUM(p.total_biaya + p.denda) AS total
    FROM pembayaran p
    WHERE DATE(p.waktu_bayar) BETWEEN ? AND ?
    GROUP BY DATE(p.waktu_bayar)
    ORDER BY tgl DESC");
$stmt->bind_param('ss', $dari, $sampai);
$stmt->execute();
$harian = $stmt->get_result();
$rows_harian = [];
$grand = ['jml'=>0, 'tunai'=>0, 'non_tunai'=>0, 'total'=>0];
while ($r = $harian->fetch_assoc()) {
    $rows_harian[] = $r;
    $grand['jml'] += $r['jml'];
    $grand['tunai'] += $r['tunai'];
    $grand['non_tunai'] += $r['non_tunai'];
    $grand['total'] += $r['total'];
}

// 2. Statistik tambahan
$total_hari = max(1, (int)((strtotime($sampai) - strtotime($dari)) / 86400) + 1);
$rata_harian = $grand['total'] / $total_hari;

// 3. Rata-rata per transaksi
$rata_transaksi = $grand['jml'] > 0 ? $grand['total'] / $grand['jml'] : 0;

// 4. Metode pembayaran terbanyak
$stmt = $koneksi->prepare("SELECT metode_pembayaran, COUNT(*) AS jml, SUM(total_biaya + denda) AS total
    FROM pembayaran WHERE DATE(waktu_bayar) BETWEEN ? AND ?
    GROUP BY metode_pembayaran ORDER BY total DESC");
$stmt->bind_param('ss', $dari, $sampai);
$stmt->execute();
$metode_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 5. Chart data (per hari, ascending)
$chart_labels = [];
$chart_values = [];
$chart_map = [];
foreach (array_reverse($rows_harian) as $r) {
    $chart_map[$r['tgl']] = (float)$r['total'];
}
$start = strtotime($dari);
$end = strtotime($sampai);
$step = 1;
$total_hari_chart = (int)(($end - $start) / 86400) + 1;
if ($total_hari_chart > 31) {
    $step = (int)ceil($total_hari_chart / 30); // max 30 titik
}
for ($t = $start; $t <= $end; $t += ($step * 86400)) {
    $tgl = date('Y-m-d', $t);
    $chart_labels[] = date('d/m', $t);
    $chart_values[] = $chart_map[$tgl] ?? 0;
}

include __DIR__ . '/../includes/header.php';
?>

<style>
html, body { overflow-x: hidden; }
.content { padding: 24px; }

/* ============================================================
   PAGE HEADER
   ============================================================ */
.lap-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.lap-header h2 {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 4px 0;
  letter-spacing: -.3px;
}
.lap-header p {
  font-size: 13.5px;
  color: #94a3b8;
  margin: 0;
}
.btn-export {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 20px;
  border-radius: 11px;
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  border: 1px solid rgba(255, 255, 255, 0.15);
  cursor: pointer;
  transition: all .25s;
  box-shadow: 0 4px 14px rgba(37,99,235,.35);
  text-decoration: none;
  font-family: inherit;
}
.btn-export:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(37,99,235,.5);
}

/* ============================================================
   FILTER CARD
   ============================================================ */
.filter-card {
  background: #111827;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  margin-bottom: 20px;
}
.filter-title {
  font-size: 14px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 14px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.preset-chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}
.preset-chip {
  padding: 8px 16px;
  border-radius: 20px;
  background: #090d16;
  color: #94a3b8;
  font-size: 12.5px;
  font-weight: 700;
  text-decoration: none;
  transition: all .2s;
  border: 1px solid rgba(255, 255, 255, 0.08);
  white-space: nowrap;
}
.preset-chip:hover {
  background: #162032;
  border-color: rgba(59, 130, 246, 0.35);
  color: #ffffff;
}
.preset-chip.active {
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  color: #fff;
  border-color: #2563eb;
  box-shadow: 0 4px 12px rgba(37,99,235,.35);
}

.custom-range {
  display: flex;
  gap: 12px;
  align-items: flex-end;
  flex-wrap: wrap;
  padding-top: 16px;
  border-top: 1px dashed rgba(255, 255, 255, 0.1);
}
.custom-range .form-group {
  margin: 0;
  flex: 1;
  min-width: 150px;
}
.custom-range label {
  font-size: 12px;
  font-weight: 700;
  color: #94a3b8;
  margin-bottom: 6px;
  display: block;
}
.custom-range input[type="date"] {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;
  font-size: 13.5px;
  font-family: inherit;
  transition: .2s;
  background: #090d16;
  color: #ffffff;
}
.custom-range input[type="date"]:focus {
  outline: none;
  border-color: #3b82f6;
  background: #0b0f19;
  box-shadow: 0 0 0 3px rgba(59,130,246,.25);
}

/* ============================================================
   KPI CARDS
   ============================================================ */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}
.kpi-card {
  background: #111827;
  border-radius: 14px;
  padding: 18px 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-left: 4px solid;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: all .25s;
  min-width: 0;
  overflow: hidden;
}
.kpi-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 28px rgba(0,0,0,.4);
}
.kpi-card.blue   { border-left-color: #3b82f6; }
.kpi-card.green  { border-left-color: #10b981; }
.kpi-card.orange { border-left-color: #f59e0b; }
.kpi-card.cyan   { border-left-color: #06b6d4; }

.kpi-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.kpi-icon {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0,0,0,.3);
}
.kpi-card.blue .kpi-icon   { background: rgba(59, 130, 246, 0.18); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }
.kpi-card.green .kpi-icon  { background: rgba(16, 185, 129, 0.18); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
.kpi-card.orange .kpi-icon { background: rgba(245, 158, 11, 0.18); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
.kpi-card.cyan .kpi-icon   { background: rgba(6, 182, 212, 0.18); color: #38bdf8; border: 1px solid rgba(6, 182, 212, 0.3); }

.kpi-label {
  font-size: 10.5px;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .6px;
  line-height: 1.3;
}
.kpi-value {
  font-size: 24px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.1;
  letter-spacing: -.5px;
  word-break: break-word;
  font-family: 'JetBrains Mono', monospace;
}
.kpi-value.small { font-size: 18px; }
.kpi-desc { font-size: 11.5px; color: #94a3b8; }

/* ============================================================
   CHART CARD
   ============================================================ */
.chart-card {
  background: #111827;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  margin-bottom: 20px;
}
.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.chart-title {
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.chart-badge {
  padding: 5px 14px;
  background: #090d16;
  color: #38bdf8;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
  border: 1px solid rgba(56, 189, 248, 0.3);
}
.chart-canvas {
  position: relative;
  height: 280px;
}
@media (max-width: 640px) {
  .chart-canvas { height: 220px; }
}

/* ============================================================
   TABLE CARD
   ============================================================ */
.table-card {
  background: #111827;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  overflow: hidden;
}
.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.table-title {
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.table-info {
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  padding: 4px 12px;
  background: #090d16;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  white-space: nowrap;
}
.table-scroll {
  overflow-x: auto;
  border-radius: 10px;
  -webkit-overflow-scrolling: touch;
}
.table-scroll::-webkit-scrollbar { height: 8px; }
.table-scroll::-webkit-scrollbar-track { background: #090d16; border-radius: 10px; }
.table-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }

.table-scroll table {
  width: 100%;
  min-width: 720px;
  border-collapse: collapse;
  font-size: 13px;
}
.table-scroll thead th {
  background: #090d16;
  padding: 12px 14px;
  text-align: left;
  font-size: 10.5px;
  font-weight: 800;
  color: #cbd5e1;
  text-transform: uppercase;
  letter-spacing: .5px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  white-space: nowrap;
}
.table-scroll tbody td {
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  color: #f1f5f9;
  vertical-align: middle;
}
.table-scroll tbody tr:last-child td { border-bottom: none; }
.table-scroll tbody tr:hover { background: rgba(59, 130, 246, 0.06); }
.table-scroll tfoot td {
  background: #090d16;
  padding: 14px;
  font-weight: 800;
  color: #ffffff;
  font-size: 13px;
  border-top: 2px solid rgba(255, 255, 255, 0.12);
}

/* ============================================================
   METHOD CHART
   ============================================================ */
.method-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  margin-top: 12px;
}
.method-item {
  padding: 14px 16px;
  border-radius: 12px;
  background: #090d16;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.method-item.tunai    { background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3); }
.method-item.qris     { background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); }
.method-item.ewallet  { background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); }

.method-label {
  font-size: 11px;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-bottom: 6px;
}
.method-value {
  font-size: 20px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1;
  margin-bottom: 4px;
  font-family: 'JetBrains Mono', monospace;
}
.method-sub {
  font-size: 12px;
  color: #94a3b8;
  font-weight: 600;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 1024px) {
  .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
  .content { padding: 16px; }
  .lap-header h2 { font-size: 18px; }
  .btn-export { width: 100%; justify-content: center; }
  .kpi-grid { grid-template-columns: 1fr; gap: 12px; }
  .kpi-card { flex-direction: row; align-items: center; gap: 14px; padding: 14px 16px; }
  .kpi-top { flex-direction: column; align-items: flex-start; gap: 8px; flex: 1; }
  .kpi-value { font-size: 20px; }
  .custom-range { flex-direction: column; align-items: stretch; }
  .custom-range .form-group { min-width: auto; }
  .custom-range .btn { width: 100%; }
}
</style>

<!-- ============================================================
     HEADER
     ============================================================ -->
<div class="lap-header">
  <div>
    <h2>📊 Laporan Pendapatan</h2>
    <p>Periode: <b><?= date('d M Y', strtotime($dari)) ?></b> s/d <b><?= date('d M Y', strtotime($sampai)) ?></b> · <?= $total_hari ?> hari</p>
  </div>
  <a href="?preset=<?= e($preset) ?>&dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>&export=1" class="btn-export">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
    Export CSV
  </a>
</div>

<!-- ============================================================
     FILTER CARD
     ============================================================ -->
<div class="filter-card">
  <h3 class="filter-title">🔍 Filter Periode</h3>

  <!-- Preset chips -->
  <div class="preset-chips">
    <a href="?preset=hari_ini" class="preset-chip <?= $preset === 'hari_ini' ? 'active' : '' ?>">📅 Hari Ini</a>
    <a href="?preset=7_hari" class="preset-chip <?= $preset === '7_hari' ? 'active' : '' ?>">📆 7 Hari Terakhir</a>
    <a href="?preset=30_hari" class="preset-chip <?= $preset === '30_hari' ? 'active' : '' ?>">📊 30 Hari Terakhir</a>
    <a href="?preset=bulan_ini" class="preset-chip <?= $preset === 'bulan_ini' ? 'active' : '' ?>">🗓️ Bulan Ini</a>
    <a href="?preset=bulan_lalu" class="preset-chip <?= $preset === 'bulan_lalu' ? 'active' : '' ?>">⏮️ Bulan Lalu</a>
    <a href="?preset=tahun_ini" class="preset-chip <?= $preset === 'tahun_ini' ? 'active' : '' ?>">📈 Tahun Ini</a>
  </div>

  <!-- Custom range -->
  <form method="get" class="custom-range">
    <input type="hidden" name="preset" value="custom">
    <div class="form-group">
      <label>📅 Dari Tanggal</label>
      <input type="date" name="dari" value="<?= e($dari) ?>" required>
    </div>
    <div class="form-group">
      <label>📅 Sampai Tanggal</label>
      <input type="date" name="sampai" value="<?= e($sampai) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary" style="padding:11px 24px;">
      🔍 Tampilkan
    </button>
  </form>
</div>

<!-- ============================================================
     KPI CARDS
     ============================================================ -->
<div class="kpi-grid">
  <div class="kpi-card green">
    <div class="kpi-top">
      <div class="kpi-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
      </div>
      <span class="kpi-label">Total Pendapatan</span>
    </div>
    <div class="kpi-value" style="font-size:20px;"><?= rupiah($grand['total']) ?></div>
    <div class="kpi-desc">Periode terpilih</div>
  </div>

  <div class="kpi-card blue">
    <div class="kpi-top">
      <div class="kpi-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"/>
          <path d="M18 17V9M13 17V5M8 17v-3"/>
        </svg>
      </div>
      <span class="kpi-label">Transaksi</span>
    </div>
    <div class="kpi-value"><?= number_format($grand['jml']) ?></div>
    <div class="kpi-desc">Rata-rata <?= rupiah($rata_transaksi) ?>/trx</div>
  </div>

  <div class="kpi-card cyan">
    <div class="kpi-top">
      <div class="kpi-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <span class="kpi-label">Rata-rata / Hari</span>
    </div>
    <div class="kpi-value" style="font-size:18px;"><?= rupiah($rata_harian) ?></div>
    <div class="kpi-desc"><?= $total_hari ?> hari</div>
  </div>

  <div class="kpi-card orange">
    <div class="kpi-top">
      <div class="kpi-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="5" width="20" height="14" rx="2"/>
          <line x1="2" y1="10" x2="22" y2="10"/>
        </svg>
      </div>
      <span class="kpi-label">Tunai / Non-Tunai</span>
    </div>
    <div class="kpi-value small"><?= rupiah($grand['tunai']) ?></div>
    <div class="kpi-desc">Non-tunai: <?= rupiah($grand['non_tunai']) ?></div>
  </div>
</div>

<!-- ============================================================
     CHART
     ============================================================ -->
<div class="chart-card">
  <div class="chart-header">
    <h3 class="chart-title">📈 Grafik Pendapatan Harian</h3>
    <span class="chart-badge"><?= date('d M', strtotime($dari)) ?> — <?= date('d M Y', strtotime($sampai)) ?></span>
  </div>
  <div class="chart-canvas">
    <canvas id="chartPendapatan"></canvas>
  </div>
</div>

<!-- ============================================================
     METODE PEMBAYARAN
     ============================================================ -->
<?php if (!empty($metode_data)): ?>
<div class="chart-card">
  <div class="chart-header">
    <h3 class="chart-title">💳 Ringkasan Metode Pembayaran</h3>
  </div>
  <div class="method-grid">
    <?php foreach ($metode_data as $m): 
      $cls = $m['metode_pembayaran'] === 'tunai' ? 'tunai' : ($m['metode_pembayaran'] === 'qris' ? 'qris' : 'ewallet');
      $label = ucfirst($m['metode_pembayaran']);
      $icon = $m['metode_pembayaran'] === 'tunai' ? '💵' : ($m['metode_pembayaran'] === 'qris' ? '📱' : '📲');
    ?>
      <div class="method-item <?= $cls ?>">
        <div class="method-label"><?= $icon ?> <?= e($label) ?></div>
        <div class="method-value"><?= rupiah($m['total']) ?></div>
        <div class="method-sub"><?= number_format($m['jml']) ?> transaksi</div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ============================================================
     TABLE DETAIL
     ============================================================ -->
<div class="table-card">
  <div class="table-header">
    <h3 class="table-title">📋 Detail Harian</h3>
    <span class="table-info"><?= count($rows_harian) ?> hari ada transaksi</span>
  </div>

  <?php if (empty($rows_harian)): ?>
    <div style="text-align:center;padding:48px 20px;color:#94a3b8;">
      <div style="font-size:48px;margin-bottom:12px;opacity:.5;">📭</div>
      <div style="font-size:14px;font-weight:600;">Belum ada transaksi di periode ini</div>
    </div>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Hari</th>
            <th>Transaksi</th>
            <th>Tunai</th>
            <th>Non-Tunai</th>
            <th style="text-align:right;">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows_harian as $r): 
            $hari_arr = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
            $hari = $hari_arr[date('l', strtotime($r['tgl']))];
          ?>
            <tr>
              <td>
                <div style="font-weight:700;"><?= date('d/m/Y', strtotime($r['tgl'])) ?></div>
              </td>
              <td><?= $hari ?></td>
              <td><span class="badge badge-info"><?= number_format($r['jml']) ?></span></td>
              <td><?= rupiah($r['tunai']) ?></td>
              <td><?= rupiah($r['non_tunai']) ?></td>
              <td style="text-align:right;"><b><?= rupiah($r['total']) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2">TOTAL</td>
            <td><b><?= number_format($grand['jml']) ?></b></td>
            <td><?= rupiah($grand['tunai']) ?></td>
            <td><?= rupiah($grand['non_tunai']) ?></td>
            <td style="text-align:right;color:#16a34a;"><?= rupiah($grand['total']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ============================================================
     CHART.JS
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('chartPendapatan').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 280);
gradient.addColorStop(0, 'rgba(34,197,94,.35)');
gradient.addColorStop(1, 'rgba(34,197,94,.02)');

new Chart(ctx, {
  type: 'line',
  data: {
    labels: <?= json_encode($chart_labels) ?>,
    datasets: [{
      label: 'Pendapatan',
      data: <?= json_encode($chart_values) ?>,
      borderColor: '#16a34a',
      backgroundColor: gradient,
      fill: true,
      tension: 0.4,
      borderWidth: 3,
      pointRadius: <?= count($chart_labels) > 15 ? 2 : 4 ?>,
      pointHoverRadius: 7,
      pointBackgroundColor: '#fff',
      pointBorderColor: '#16a34a',
      pointBorderWidth: 2.5
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { intersect: false, mode: 'index' },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0f172a',
        padding: 12,
        cornerRadius: 8,
        titleColor: '#94a3b8',
        titleFont: { size: 11, weight: '600' },
        bodyColor: '#fff',
        bodyFont: { size: 13, weight: '700' },
        displayColors: false,
        callbacks: {
          label: ctx => '💰 Rp ' + ctx.parsed.y.toLocaleString('id-ID')
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: '#f1f5f9' },
        ticks: {
          color: '#94a3b8',
          font: { size: 10.5, weight: '600' },
          callback: v => {
            if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
            if (v >= 1000) return 'Rp ' + (v/1000).toFixed(0) + 'rb';
            return 'Rp ' + v;
          }
        }
      },
      x: {
        grid: { display: false },
        ticks: { color: '#94a3b8', font: { size: 10.5, weight: '600' }, maxRotation: 45, minRotation: 0 }
      }
    }
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>