<?php
// ============================================================
// RIWAYAT TRANSAKSI - OPERATOR (Size-calibrated)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_operator();

$page_title = 'Riwayat Transaksi';
$page_subtitle = 'Semua transaksi parkir';
$uid = user_id();
$user_name = $_SESSION['nama_lengkap'] ?? 'Operator';

// ============================================================
// FILTER
// ============================================================
$filter = $_GET['filter'] ?? 'semua';
$search = trim($_GET['q'] ?? '');
$scope  = $_GET['scope'] ?? 'semua';
$dari   = $_GET['dari'] ?? '';
$sampai = $_GET['sampai'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($filter === 'aktif') {
    $where .= " AND t.status IN ('aktif', 'menunggu_approval')";
} elseif ($filter === 'menunggu') {
    $where .= " AND t.status = 'menunggu_approval'";
} elseif ($filter === 'selesai') {
    $where .= " AND t.status = 'selesai'";
} elseif ($filter === 'batal') {
    $where .= " AND t.status = 'batal'";
}

if ($scope === 'saya') {
    $where .= " AND (t.operator_id = ? OR p.kasir_id = ?)";
    $params[] = $uid;
    $params[] = $uid;
    $types .= 'ii';
}

if ($dari) {
    $where .= " AND DATE(t.waktu_masuk) >= ?";
    $params[] = $dari;
    $types .= 's';
}
if ($sampai) {
    $where .= " AND DATE(t.waktu_masuk) <= ?";
    $params[] = $sampai;
    $types .= 's';
}

if ($search !== '') {
    $where .= " AND (t.plat_nomor LIKE ? OR t.kode_tiket LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $types .= 'ss';
}

// Statistik
$count_sql = "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN t.status='selesai' THEN 1 ELSE 0 END) AS selesai,
    SUM(CASE WHEN t.status IN ('aktif','menunggu_approval') THEN 1 ELSE 0 END) AS aktif,
    SUM(CASE WHEN t.status='menunggu_approval' THEN 1 ELSE 0 END) AS menunggu,
    COALESCE(SUM(p.total_biaya + p.denda), 0) AS total_pendapatan
    FROM transaksi_parkir t
    LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
    $where";

if (!empty($params)) {
    $stmt = $koneksi->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stat = $stmt->get_result()->fetch_assoc();
} else {
    $stat = $koneksi->query($count_sql)->fetch_assoc();
}

// Stat personal
$stmt = $koneksi->prepare("SELECT 
    COUNT(*) AS total_input,
    SUM(CASE WHEN DATE(waktu_masuk) = CURDATE() THEN 1 ELSE 0 END) AS input_hari_ini
    FROM transaksi_parkir WHERE operator_id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$stat_personal = $stmt->get_result()->fetch_assoc();

// Export CSV
if (isset($_GET['export'])) {
    $sql = "SELECT 
        t.id_transaksi, t.kode_tiket, t.plat_nomor, t.jenis_kendaraan,
        s.kode_slot, s.zona, t.waktu_masuk, t.waktu_keluar, t.durasi_jam,
        t.status, t.sumber,
        p.total_biaya, p.denda, p.metode_pembayaran, p.waktu_bayar
        FROM transaksi_parkir t
        JOIN slot_parkir s ON t.id_slot = s.id_slot
        LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
        $where
        ORDER BY t.waktu_masuk DESC";
    
    $stmt = $koneksi->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $data = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=riwayat_operator_' . date('Ymd_His') . '.csv');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($out, ['RIWAYAT TRANSAKSI PARKIR']);
    fputcsv($out, [$perusahaan['nama_perusahaan']]);
    fputcsv($out, ['Dicetak oleh: ' . $user_name]);
    fputcsv($out, ['Tanggal Export: ' . date('d/m/Y H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, ['ID', 'Kode Tiket', 'Plat', 'Jenis', 'Slot', 'Zona', 'Masuk', 'Keluar', 'Durasi (jam)', 'Status', 'Sumber', 'Biaya', 'Denda', 'Metode', 'Waktu Bayar']);

    while ($r = $data->fetch_assoc()) {
        fputcsv($out, [
            $r['id_transaksi'], $r['kode_tiket'], $r['plat_nomor'], $r['jenis_kendaraan'],
            $r['kode_slot'], $r['zona'], $r['waktu_masuk'], $r['waktu_keluar'] ?? '-',
            $r['durasi_jam'] ?? 0, $r['status'], $r['sumber'],
            $r['total_biaya'] ?? 0, $r['denda'] ?? 0,
            $r['metode_pembayaran'] ?? '-', $r['waktu_bayar'] ?? '-'
        ]);
    }
    fclose($out);
    exit;
}

// Data tabel
$pagi = paginate($stat['total'], 15);

$sql = "SELECT 
    t.*, s.kode_slot, s.zona,
    op.nama_lengkap AS operator_nama,
    pl.nama_lengkap AS pelanggan_nama,
    p.total_biaya, p.denda, p.metode_pembayaran, p.waktu_bayar
    FROM transaksi_parkir t
    JOIN slot_parkir s ON t.id_slot = s.id_slot
    LEFT JOIN users op ON t.operator_id = op.id_user
    LEFT JOIN users pl ON t.user_id = pl.id_user
    LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
    $where
    ORDER BY t.waktu_masuk DESC
    LIMIT {$pagi['per_page']} OFFSET {$pagi['offset']}";

if (!empty($params)) {
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $riwayat = $stmt->get_result();
} else {
    $riwayat = $koneksi->query($sql);
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================================
   RIWAYAT OPERATOR — SIZE-CALIBRATED
   ============================================================ */
html, body { overflow-x: hidden; }
.content { padding: 22px; }

/* ---------- PAGE HEADER ---------- */
.rt-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 14px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.rt-header-left { min-width: 0; flex: 1; }
.rt-header-left h2 {
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 3px 0;
  letter-spacing: -.3px;
  line-height: 1.2;
}
.rt-header-left p {
  font-size: 12.5px;
  color: #64748b;
  margin: 0;
  line-height: 1.5;
}
.rt-header-left p b { color: #0f172a; font-weight: 700; }

.rt-btn-export {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 16px;
  border-radius: 10px;
  background: linear-gradient(135deg, #16a34a, #15803d);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  transition: all .25s;
  box-shadow: 0 4px 12px rgba(22,163,74,.25);
  text-decoration: none;
  font-family: inherit;
  white-space: nowrap;
  flex-shrink: 0;
}
.rt-btn-export:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(22,163,74,.35);
}

/* ---------- STAT GRID ---------- */
.rt-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
  margin-bottom: 16px;
}
.rt-stat {
  background: #fff;
  border-radius: 12px;
  padding: 16px 18px;
  border: 1px solid #e2e8f0;
  border-left: 4px solid;
  display: flex;
  flex-direction: column;
  gap: 8px;
  transition: all .25s;
  min-width: 0;
  overflow: hidden;
}
.rt-stat:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(30,58,138,.08);
}
.rt-stat.blue   { border-left-color: #3b82f6; }
.rt-stat.green  { border-left-color: #16a34a; }
.rt-stat.orange { border-left-color: #f59e0b; }
.rt-stat.purple { border-left-color: #8b5cf6; }

.rt-stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.rt-stat-icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 3px 10px rgba(0,0,0,.12);
}
.rt-stat-icon svg { width: 20px; height: 20px; }
.rt-stat.blue .rt-stat-icon   { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.rt-stat.green .rt-stat-icon  { background: linear-gradient(135deg, #22c55e, #15803d); }
.rt-stat.orange .rt-stat-icon { background: linear-gradient(135deg, #fbbf24, #d97706); }
.rt-stat.purple .rt-stat-icon { background: linear-gradient(135deg, #a78bfa, #7c3aed); }

.rt-stat-label {
  font-size: 10px;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .5px;
  line-height: 1.3;
}
.rt-stat-value {
  font-size: 22px;
  font-weight: 800;
  color: #0f172a;
  line-height: 1.1;
  letter-spacing: -.5px;
  word-break: break-word;
}
.rt-stat-value.small { font-size: 17px; }
.rt-stat-desc { font-size: 11px; color: #64748b; line-height: 1.3; }

/* ---------- FILTER CARD ---------- */
.rt-filter {
  background: #fff;
  border-radius: 12px;
  padding: 18px 20px;
  border: 1px solid #e2e8f0;
  margin-bottom: 16px;
}
.rt-filter-title {
  font-size: 13.5px;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 12px 0;
  display: flex;
  align-items: center;
  gap: 7px;
  line-height: 1.3;
}

.rt-filter-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 12px;
}
.rt-filter-row:last-of-type { margin-bottom: 0; }

.rt-filter-label {
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  margin-right: 4px;
  flex-shrink: 0;
}

.rt-chip {
  padding: 7px 14px;
  border-radius: 18px;
  background: #f1f5f9;
  color: #475569;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all .2s;
  border: 1px solid transparent;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  line-height: 1.2;
}
.rt-chip:hover {
  background: #e2e8f0;
  border-color: #cbd5e1;
  color: #1e3a8a;
}
.rt-chip.active {
  background: linear-gradient(135deg, #1e3a8a, #3b82f6);
  color: #fff;
  border-color: #1e3a8a;
  box-shadow: 0 3px 10px rgba(30,58,138,.22);
}
.rt-chip.scope.active {
  background: linear-gradient(135deg, #16a34a, #15803d);
  border-color: #16a34a;
  box-shadow: 0 3px 10px rgba(22,163,74,.22);
}

/* ---------- SEARCH FORM ---------- */
.rt-search {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr auto auto;
  gap: 8px;
  align-items: flex-end;
  padding-top: 14px;
  border-top: 1px dashed #e2e8f0;
  margin-top: 14px;
}
.rt-search-group { min-width: 0; }
.rt-search-group label {
  font-size: 11px;
  font-weight: 700;
  color: #475569;
  margin-bottom: 5px;
  display: block;
  text-transform: uppercase;
  letter-spacing: .3px;
}
.rt-search-input {
  width: 100%;
  padding: 9px 12px;
  border: 1.5px solid #e2e8f0;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  transition: .2s;
  background: #f8fafc;
  min-width: 0;
  color: #0f172a;
}
.rt-search-input:focus {
  outline: none;
  border-color: #3b82f6;
  background: #fff;
  box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
.rt-search-btn {
  padding: 9px 16px;
  border-radius: 8px;
  border: none;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  font-family: inherit;
  white-space: nowrap;
  line-height: 1.3;
}
.rt-search-btn.primary {
  background: linear-gradient(135deg, #1e3a8a, #3b82f6);
  color: #fff;
  box-shadow: 0 3px 10px rgba(30,58,138,.22);
}
.rt-search-btn.primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 5px 14px rgba(30,58,138,.32);
}
.rt-search-btn.secondary {
  background: #f1f5f9;
  color: #475569;
  border: 1px solid #e2e8f0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}
.rt-search-btn.secondary:hover { background: #e2e8f0; }

/* ---------- TABLE CARD ---------- */
.rt-table-card {
  background: #fff;
  border-radius: 12px;
  padding: 18px;
  border: 1px solid #e2e8f0;
  overflow: hidden;
}
.rt-table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}
.rt-table-title {
  font-size: 14px;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 7px;
  line-height: 1.3;
}
.rt-table-info {
  font-size: 11.5px;
  font-weight: 600;
  color: #64748b;
  padding: 4px 12px;
  background: #f1f5f9;
  border-radius: 16px;
  white-space: nowrap;
}
.rt-table-info b { color: #1e3a8a; font-weight: 800; }

.rt-table-scroll {
  overflow-x: auto;
  border-radius: 8px;
  -webkit-overflow-scrolling: touch;
  max-width: 100%;
}
.rt-table-scroll::-webkit-scrollbar { height: 7px; }
.rt-table-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
.rt-table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
.rt-table-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.rt-table-scroll table {
  width: 100%;
  min-width: 950px;
  border-collapse: collapse;
  font-size: 12.5px;
}
.rt-table-scroll thead th {
  background: #f8fafc;
  padding: 10px 12px;
  text-align: left;
  font-size: 10px;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .5px;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.rt-table-scroll tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  vertical-align: middle;
  line-height: 1.4;
}
.rt-table-scroll tbody tr:last-child td { border-bottom: none; }
.rt-table-scroll tbody tr:hover { background: #f8fafc; }
.rt-table-scroll tbody tr.waiting { background: #fffbeb; }
.rt-table-scroll tbody tr.waiting:hover { background: #fef3c7; }

/* ---------- EMPTY ---------- */
.rt-empty {
  text-align: center;
  padding: 50px 20px;
  color: #94a3b8;
}
.rt-empty-icon {
  font-size: 48px;
  margin-bottom: 12px;
  opacity: .5;
}
.rt-empty-title {
  font-size: 14px;
  font-weight: 700;
  color: #64748b;
  margin-bottom: 5px;
}
.rt-empty-sub {
  font-size: 12.5px;
  color: #94a3b8;
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 1100px) {
  .rt-search { grid-template-columns: 1fr 1fr 1fr; }
  .rt-search > *:nth-child(4) { grid-column: 1; }
  .rt-search > *:nth-child(5) { grid-column: 2; }
}
@media (max-width: 1024px) {
  .rt-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 768px) {
  .rt-search { grid-template-columns: 1fr 1fr; }
  .rt-search > *:nth-child(1) { grid-column: 1 / -1; }
  .rt-search > *:nth-child(4) { grid-column: 1; }
  .rt-search > *:nth-child(5) { grid-column: 2; }
}
@media (max-width: 640px) {
  .content { padding: 14px; }
  .rt-header-left h2 { font-size: 17px; }
  .rt-btn-export { width: 100%; justify-content: center; }
  .rt-stats { grid-template-columns: 1fr; gap: 10px; }
  .rt-stat {
    flex-direction: row;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
  }
  .rt-stat-top { flex-direction: column; align-items: flex-start; gap: 6px; flex: 1; }
  .rt-stat-value { font-size: 19px; }
  .rt-stat-value.small { font-size: 16px; }
  .rt-filter { padding: 14px; }
  .rt-search { grid-template-columns: 1fr; }
  .rt-search > * { grid-column: 1 !important; }
  .rt-search-btn { width: 100%; }
  .rt-table-card { padding: 14px; }
}
</style>

<!-- ============================================================
     HEADER
     ============================================================ -->
<div class="rt-header">
  <div class="rt-header-left">
    <h2>📜 Riwayat Transaksi</h2>
    <p>Halo <b><?= e($user_name) ?></b> · Total <b><?= number_format($stat_personal['total_input'] ?? 0) ?></b> transaksi pernah Anda input · <b><?= number_format($stat_personal['input_hari_ini'] ?? 0) ?></b> hari ini</p>
  </div>
  <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="rt-btn-export">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="7 10 12 15 17 10"/>
      <line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Export CSV
  </a>
</div>

<!-- ============================================================
     STATISTIK
     ============================================================ -->
<div class="rt-stats">
  <div class="rt-stat blue">
    <div class="rt-stat-top">
      <div class="rt-stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3v18h18"/>
          <path d="M18 17V9M13 17V5M8 17v-3"/>
        </svg>
      </div>
      <span class="rt-stat-label">Total Transaksi</span>
    </div>
    <div class="rt-stat-value"><?= number_format($stat['total'] ?? 0) ?></div>
    <div class="rt-stat-desc">Sesuai filter</div>
  </div>

  <div class="rt-stat green">
    <div class="rt-stat-top">
      <div class="rt-stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
      <span class="rt-stat-label">Selesai</span>
    </div>
    <div class="rt-stat-value"><?= number_format($stat['selesai'] ?? 0) ?></div>
    <div class="rt-stat-desc">Transaksi tuntas</div>
  </div>

  <div class="rt-stat orange">
    <div class="rt-stat-top">
      <div class="rt-stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <polyline points="12 6 12 12 16 14"/>
        </svg>
      </div>
      <span class="rt-stat-label">Aktif / Menunggu</span>
    </div>
    <div class="rt-stat-value"><?= number_format($stat['aktif'] ?? 0) ?></div>
    <div class="rt-stat-desc"><?= $stat['menunggu'] ?? 0 ?> menunggu approval</div>
  </div>

  <div class="rt-stat purple">
    <div class="rt-stat-top">
      <div class="rt-stat-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="1" x2="12" y2="23"/>
          <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
      </div>
      <span class="rt-stat-label">Total Pendapatan</span>
    </div>
    <div class="rt-stat-value small"><?= rupiah($stat['total_pendapatan'] ?? 0) ?></div>
    <div class="rt-stat-desc">Sesuai filter</div>
  </div>
</div>

<!-- ============================================================
     FILTER
     ============================================================ -->
<div class="rt-filter">
  <h3 class="rt-filter-title">🔍 Filter & Pencarian</h3>

  <div class="rt-filter-row">
    <span class="rt-filter-label">Cakupan:</span>
    <a href="?<?= http_build_query(array_merge($_GET, ['scope' => 'semua', 'page' => 1])) ?>" 
       class="rt-chip scope <?= $scope === 'semua' ? 'active' : '' ?>">
      👥 Semua Transaksi
    </a>
    <a href="?<?= http_build_query(array_merge($_GET, ['scope' => 'saya', 'page' => 1])) ?>" 
       class="rt-chip scope <?= $scope === 'saya' ? 'active' : '' ?>">
      ⭐ Transaksi Saya
    </a>
  </div>

  <div class="rt-filter-row">
    <span class="rt-filter-label">Status:</span>
    <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'semua', 'page' => 1])) ?>" 
       class="rt-chip <?= $filter === 'semua' ? 'active' : '' ?>">📋 Semua</a>
    <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'aktif', 'page' => 1])) ?>" 
       class="rt-chip <?= $filter === 'aktif' ? 'active' : '' ?>">🅿️ Parkir</a>
    <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'menunggu', 'page' => 1])) ?>" 
       class="rt-chip <?= $filter === 'menunggu' ? 'active' : '' ?>">⏳ Menunggu</a>
    <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'selesai', 'page' => 1])) ?>" 
       class="rt-chip <?= $filter === 'selesai' ? 'active' : '' ?>">✅ Selesai</a>
    <a href="?<?= http_build_query(array_merge($_GET, ['filter' => 'batal', 'page' => 1])) ?>" 
       class="rt-chip <?= $filter === 'batal' ? 'active' : '' ?>">❌ Batal</a>
  </div>

  <form method="get" class="rt-search">
    <input type="hidden" name="filter" value="<?= e($filter) ?>">
    <input type="hidden" name="scope" value="<?= e($scope) ?>">
    
    <div class="rt-search-group">
      <label>🔍 Cari</label>
      <input type="text" name="q" value="<?= e($search) ?>" 
             placeholder="Plat nomor / kode tiket..." 
             class="rt-search-input">
    </div>
    <div class="rt-search-group">
      <label>📅 Dari</label>
      <input type="date" name="dari" value="<?= e($dari) ?>" class="rt-search-input">
    </div>
    <div class="rt-search-group">
      <label>📅 Sampai</label>
      <input type="date" name="sampai" value="<?= e($sampai) ?>" class="rt-search-input">
    </div>
    <button type="submit" class="rt-search-btn primary">🔍 Filter</button>
    <?php if ($search || $dari || $sampai): ?>
      <a href="<?= PATH_OPERATOR ?>riwayat.php" class="rt-search-btn secondary">✕ Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- ============================================================
     TABEL
     ============================================================ -->
<div class="rt-table-card">
  <div class="rt-table-header">
    <h3 class="rt-table-title">📋 Daftar Transaksi</h3>
    <span class="rt-table-info">
      <?php if ($search || $dari || $sampai || $filter !== 'semua' || $scope !== 'semua'): ?>
        Hasil: <b><?= number_format($stat['total'] ?? 0) ?></b>
      <?php else: ?>
        Total: <b><?= number_format($stat['total'] ?? 0) ?></b>
      <?php endif; ?>
    </span>
  </div>

  <?php if ($riwayat->num_rows === 0): ?>
    <div class="rt-empty">
      <div class="rt-empty-icon">📭</div>
      <div class="rt-empty-title">Tidak ada transaksi</div>
      <div class="rt-empty-sub">
        <?php if ($search || $dari || $sampai): ?>
          Coba ubah filter atau reset pencarian
        <?php elseif ($scope === 'saya'): ?>
          Anda belum pernah input transaksi
        <?php else: ?>
          Belum ada transaksi di sistem
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="rt-table-scroll">
      <table>
        <thead>
          <tr>
            <th>Tanggal & Jam</th>
            <th>Kode Tiket</th>
            <th>Plat</th>
            <th>Jenis</th>
            <th>Slot</th>
            <th>Durasi</th>
            <th>Biaya</th>
            <th>Sumber</th>
            <th>Operator</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($r = $riwayat->fetch_assoc()): 
            $is_waiting = $r['status'] === 'menunggu_approval';
            $is_mine = ($r['operator_id'] == $uid || ($r['disetujui_oleh'] ?? 0) == $uid);
          ?>
            <tr class="<?= $is_waiting ? 'waiting' : '' ?>">
              <td>
                <div style="font-weight:600;"><?= date('d/m/Y', strtotime($r['waktu_masuk'])) ?></div>
                <div style="font-size:10.5px;color:#94a3b8;"><?= date('H:i:s', strtotime($r['waktu_masuk'])) ?></div>
              </td>
              <td>
                <code style="background:#f1f5f9;padding:2px 7px;border-radius:5px;font-size:10.5px;display:inline-block;">
                  <?= e($r['kode_tiket']) ?>
                </code>
                <?php if ($is_mine): ?>
                  <span class="badge badge-success" style="font-size:9px;padding:1px 5px;margin-left:2px;">MINE</span>
                <?php endif; ?>
              </td>
              <td><b style="letter-spacing:.5px;"><?= e($r['plat_nomor']) ?></b></td>
              <td>
                <span class="badge <?= $r['jenis_kendaraan'] === 'motor' ? 'badge-info' : 'badge-warning' ?>" style="font-size:10px;">
                  <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️' : '🚗' ?>
                </span>
              </td>
              <td>
                <div style="font-weight:600;"><?= e($r['kode_slot']) ?></div>
                <div style="font-size:10px;color:#94a3b8;">Zona <?= e($r['zona']) ?></div>
              </td>
              <td>
                <?php if ($r['waktu_keluar']): ?>
                  <b><?= (int)$r['durasi_jam'] ?> jam</b>
                <?php else: ?>
                  <span style="color:#f59e0b;font-weight:600;">⏳ <?= format_durasi($r['waktu_masuk']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['total_biaya']): ?>
                  <b style="color:#16a34a;"><?= rupiah($r['total_biaya'] + ($r['denda'] ?? 0)) ?></b>
                  <?php if ($r['metode_pembayaran']): ?>
                    <div style="font-size:10px;color:#94a3b8;"><?= e($r['metode_pembayaran']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:#94a3b8;">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['sumber'] === 'kiosk'): ?>
                  <span class="badge badge-info" style="font-size:9.5px;">🖥️ Kiosk</span>
                <?php elseif ($r['sumber'] === 'pelanggan'): ?>
                  <span class="badge badge-primary" style="font-size:9.5px;">👤 Pelanggan</span>
                <?php else: ?>
                  <span class="badge badge-gray" style="font-size:9.5px;">👨‍🔧 Operator</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['operator_nama']): ?>
                  <div style="font-size:11.5px;font-weight:600;"><?= e($r['operator_nama']) ?></div>
                <?php elseif ($r['pelanggan_nama']): ?>
                  <div style="font-size:11px;color:#64748b;">👤 <?= e($r['pelanggan_nama']) ?></div>
                <?php else: ?>
                  <span style="color:#94a3b8;">-</span>
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
                  <span class="badge badge-gray">❌ Batal</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <?= render_pagination($pagi) ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>