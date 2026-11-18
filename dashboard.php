<?php
// ============================================================
// DASHBOARD UTAMA - PT. PARKIR BOGOR (ENTERPRISE EDITION)
// Standard: Modern & Responsive Dark Enterprise Mobility Console
// Matching: International Smart Mobility Platform (index.php)
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

cek_login();

$uid = user_id();
$user_name = user_name();
$role = user_role();

// 1. STATISTIK OPERASIONAL
$ring = [
    'masuk_hari_ini' => 0,
    'masih_parkir' => 0,
    'keluar_hari_ini' => 0,
    'pendapatan_hari_ini' => 0,
    'slot_kosong' => 0,
    'slot_total' => 0
];

try {
    $qRing = $koneksi->query("SELECT * FROM v_ringkasan_hari_ini");
    if ($qRing && $qRing->num_rows > 0) {
        $ring = $qRing->fetch_assoc();
    } else {
        // Fallback query langsung
        $rM = $koneksi->query("SELECT COUNT(*) t FROM transaksi_parkir WHERE DATE(waktu_masuk) = CURDATE()")->fetch_assoc();
        $rA = $koneksi->query("SELECT COUNT(*) t FROM transaksi_parkir WHERE status = 'aktif'")->fetch_assoc();
        $rK = $koneksi->query("SELECT COUNT(*) t FROM transaksi_parkir WHERE status = 'selesai' AND DATE(waktu_keluar) = CURDATE()")->fetch_assoc();
        $rP = $koneksi->query("SELECT SUM(total_biaya + IFNULL(denda,0)) t FROM pembayaran WHERE DATE(waktu_bayar) = CURDATE()")->fetch_assoc();
        $rSk = $koneksi->query("SELECT COUNT(*) t FROM slot_parkir WHERE status='kosong'")->fetch_assoc();
        $rSt = $koneksi->query("SELECT COUNT(*) t FROM slot_parkir")->fetch_assoc();

        $ring['masuk_hari_ini'] = (int)($rM['t'] ?? 0);
        $ring['masih_parkir'] = (int)($rA['t'] ?? 0);
        $ring['keluar_hari_ini'] = (int)($rK['t'] ?? 0);
        $ring['pendapatan_hari_ini'] = (float)($rP['t'] ?? 0);
        $ring['slot_kosong'] = (int)($rSk['t'] ?? 0);
        $ring['slot_total'] = (int)($rSt['t'] ?? 0);
    }
} catch (Exception $e) {}

// 2. KETERSEDIAAN SLOT PARKIR
$slot_motor = ['total' => 0, 'kosong' => 0, 'terisi' => 0];
$slot_mobil = ['total' => 0, 'kosong' => 0, 'terisi' => 0];

try {
    $qMotor = $koneksi->query("SELECT 
        COUNT(*) total,
        SUM(CASE WHEN status='kosong' THEN 1 ELSE 0 END) kosong,
        SUM(CASE WHEN status='terisi' THEN 1 ELSE 0 END) terisi
        FROM slot_parkir WHERE jenis_kendaraan='motor'")->fetch_assoc();
    if ($qMotor) {
        $slot_motor = [
            'total'  => (int)$qMotor['total'],
            'kosong' => (int)($qMotor['kosong'] ?? 0),
            'terisi' => (int)($qMotor['terisi'] ?? 0),
        ];
    }

    $qMobil = $koneksi->query("SELECT 
        COUNT(*) total,
        SUM(CASE WHEN status='kosong' THEN 1 ELSE 0 END) kosong,
        SUM(CASE WHEN status='terisi' THEN 1 ELSE 0 END) terisi
        FROM slot_parkir WHERE jenis_kendaraan='mobil'")->fetch_assoc();
    if ($qMobil) {
        $slot_mobil = [
            'total'  => (int)$qMobil['total'],
            'kosong' => (int)($qMobil['kosong'] ?? 0),
            'terisi' => (int)($qMobil['terisi'] ?? 0),
        ];
    }
} catch (Exception $e) {}

$pct_motor = $slot_motor['total'] > 0 ? round(($slot_motor['terisi'] / $slot_motor['total']) * 100) : 0;
$pct_mobil = $slot_mobil['total'] > 0 ? round(($slot_mobil['terisi'] / $slot_mobil['total']) * 100) : 0;
$persen_tersedia = ($ring['slot_total'] ?? 0) > 0 ? round((($ring['slot_kosong'] ?? 0) / $ring['slot_total']) * 100) : 0;

// 3. CHART PENDAPATAN 7 HARI TERAKHIR
$chartData = [];
try {
    $q = $koneksi->query("SELECT DATE(waktu_bayar) tgl, SUM(total_biaya + IFNULL(denda,0)) total 
        FROM pembayaran 
        WHERE waktu_bayar >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(waktu_bayar) ORDER BY tgl");
    if ($q) {
        while ($r = $q->fetch_assoc()) $chartData[$r['tgl']] = (float)$r['total'];
    }
} catch (Exception $e) {}

$labels = [];
$values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('d/m', strtotime($d));
    $values[] = $chartData[$d] ?? 0;
}

// 4. KENDARAAN AKTIF TERKINI (DENGAN PAGINATION)
$total_aktif = 0;
try {
    $rTotA = $koneksi->query("SELECT COUNT(*) AS total FROM transaksi_parkir WHERE status = 'aktif' AND waktu_keluar IS NULL");
    if ($rTotA) $total_aktif = (int)($rTotA->fetch_assoc()['total'] ?? 0);
} catch (Exception $e) {}
$pagi_aktif = paginate($total_aktif, 5, 'page');

$aktif = null;
try {
    $aktif = $koneksi->query("SELECT * FROM v_transaksi_aktif ORDER BY waktu_masuk DESC LIMIT {$pagi_aktif['per_page']} OFFSET {$pagi_aktif['offset']}");
} catch (Exception $e) {}
if ($total_aktif === 0 && $aktif && $aktif->num_rows > 0) {
    $total_aktif = $aktif->num_rows;
    $pagi_aktif = paginate($total_aktif, 5, 'page');
}

// 5. DATA TARIF UNTUK KALKULATOR
$tarifList = [];
try {
    $qTarif = $koneksi->query("SELECT * FROM tarif_parkir");
    if ($qTarif) {
        while ($tr = $qTarif->fetch_assoc()) {
            $tarifList[$tr['jenis_kendaraan']] = [
                'per_jam' => (float)$tr['tarif_per_jam'],
                'minimal' => (float)$tr['tarif_minimal'],
                'maksimal' => (float)$tr['tarif_maksimal'],
            ];
        }
    }
} catch (Exception $e) {}
if (!isset($tarifList['motor'])) $tarifList['motor'] = ['per_jam' => 2000, 'minimal' => 2000, 'maksimal' => 20000];
if (!isset($tarifList['mobil'])) $tarifList['mobil'] = ['per_jam' => 5000, 'minimal' => 5000, 'maksimal' => 50000];

// 6. LATEST TRANSAKSI UNTUK ANPR SCANNER
$latest_trx = null;
try {
    $qLatest = $koneksi->query("
        SELECT t.*, s.kode_slot, s.zona
        FROM transaksi_parkir t
        JOIN slot_parkir s ON t.id_slot = s.id_slot
        ORDER BY t.id_transaksi DESC
        LIMIT 1
    ");
    if ($qLatest) $latest_trx = $qLatest->fetch_assoc();
} catch (Exception $e) {}

$page_title = 'Dashboard Operasional';
$page_subtitle = 'Monitoring Pos Gerbang & Alur Kendaraan Real-Time';
include __DIR__ . '/includes/header.php';
?>

<style>
/* ================================================================
   DASHBOARD UTAMA - GAYA PERSIS TAMPILAN LANDING PAGE
   Deep obsidian, glowing telemetry, ANPR Terminal, and dark cards
   ================================================================ */

/* BANNER */
.dash-banner {
  background: linear-gradient(135deg, #0c1222 0%, #111827 50%, #0f172a 100%);
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 20px;
  padding: 28px 32px;
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 18px;
  box-shadow: 0 10px 35px rgba(0, 0, 0, 0.4), 0 0 30px rgba(37, 99, 235, 0.15);
  position: relative;
  overflow: hidden;
}
.dash-banner-content h2 {
  font-size: 24px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 6px 0;
  letter-spacing: -0.5px;
}
.dash-banner-content .grad-text {
  background: linear-gradient(135deg, #60a5fa 0%, #38bdf8 50%, #818cf8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.dash-banner-content p {
  color: #94a3b8;
  margin: 0;
  font-size: 14px;
}

.telemetry-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.3);
  border-radius: 9999px;
  font-size: 12.5px;
  font-weight: 700;
  color: #34d399;
}
.radar-pulse {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.35);
  animation: pulseRadar 2s infinite;
}
@keyframes pulseRadar {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.6; }
}

/* KPI CARDS */
.stats-grid-enterprise {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 24px;
}
@media (max-width: 1100px) { .stats-grid-enterprise { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 600px)  { .stats-grid-enterprise { grid-template-columns: 1fr; } }

.stat-card-ent {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 22px 24px;
  position: relative;
  overflow: hidden;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
  transition: all 0.25s ease;
}
.stat-card-ent:hover {
  transform: translateY(-3px);
  border-color: rgba(59, 130, 246, 0.4);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}
.stat-card-ent::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
}
.stat-card-ent.blue::before   { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.stat-card-ent.amber::before  { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.stat-card-ent.green::before  { background: linear-gradient(90deg, #10b981, #34d399); }
.stat-card-ent.cyan::before   { background: linear-gradient(90deg, #06b6d4, #38bdf8); }

.stat-card-flex {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 12px;
}
.stat-card-icon {
  width: 44px; height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
}
.stat-card-icon.blue  { background: rgba(59,130,246,0.12); color: #60a5fa; border: 1px solid rgba(59,130,246,0.25); }
.stat-card-icon.amber { background: rgba(245,158,11,0.12); color: #fbbf24; border: 1px solid rgba(245,158,11,0.25); }
.stat-card-icon.green { background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.25); }
.stat-card-icon.cyan  { background: rgba(6,182,212,0.12); color: #38bdf8; border: 1px solid rgba(6,182,212,0.25); }

.stat-ent-value {
  font-size: 32px;
  font-weight: 800;
  color: #ffffff;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: -1px;
  line-height: 1.1;
  margin-bottom: 4px;
}
.stat-ent-value.green { color: #34d399; }
.stat-ent-value.cyan  { color: #38bdf8; }

.stat-ent-label {
  font-size: 11.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #94a3b8;
  margin-bottom: 2px;
}
.stat-ent-desc {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

/* TWO-COL SHOWCASE (ANPR TERMINAL + OCCUPANCY) */
.two-col-showcase {
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}
@media (max-width: 1024px) { .two-col-showcase { grid-template-columns: 1fr; } }

.terminal-window {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 20px;
  box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5), 0 0 30px rgba(37, 99, 235, 0.15);
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
}
.terminal-header {
  background: #090d16;
  padding: 14px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.terminal-dots { display: flex; gap: 8px; }
.terminal-dot { width: 11px; height: 11px; border-radius: 50%; }
.terminal-dot.red { background: #ef4444; }
.terminal-dot.yellow { background: #f59e0b; }
.terminal-dot.green { background: #10b981; }
.terminal-title { font-size: 12px; font-family: 'JetBrains Mono', monospace; color: #94a3b8; font-weight: 600; }

.terminal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 16px;
  flex: 1;
  justify-content: center;
}
.anpr-scan-card {
  background: #090d16;
  border: 1px solid rgba(59, 130, 246, 0.3);
  border-radius: 14px;
  padding: 18px;
  position: relative;
  overflow: hidden;
}
.anpr-scan-card::after {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, #38bdf8, transparent);
  animation: scanLaser 3s infinite linear;
}
@keyframes scanLaser {
  0%   { transform: translateY(0); }
  50%  { transform: translateY(75px); }
  100% { transform: translateY(0); }
}

.plate-display {
  background: #020617;
  border: 2px solid #334155;
  border-radius: 8px;
  padding: 12px 18px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: 4px;
  color: #ffffff;
  text-align: center;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.8);
  transition: opacity 0.25s ease;
}

.gate-info-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.gate-info-box {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
  padding: 10px 14px;
}
.gate-info-box small {
  font-size: 10px;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 700;
  display: block;
  margin-bottom: 3px;
}
.gate-info-box b {
  font-size: 13.5px;
  color: #e2e8f0;
  font-family: 'JetBrains Mono', monospace;
}

/* TELEMETRY CARD */
.telemetry-card {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.telemetry-item {
  background: #090d16;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 18px 20px;
  margin-bottom: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.telemetry-header { display: flex; align-items: center; justify-content: space-between; }
.telemetry-progress-bg {
  height: 8px;
  background: #1e293b;
  border-radius: 9999px;
  overflow: hidden;
  position: relative;
}
.telemetry-progress-fill {
  height: 100%;
  border-radius: 9999px;
  transition: width 1s ease;
}

/* BENTO ACTIONS */
.bento-actions-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 24px;
}
@media (max-width: 960px) { .bento-actions-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .bento-actions-grid { grid-template-columns: 1fr; } }

.bento-action-card {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
  transition: all 0.25s ease;
}
.bento-action-card:hover {
  transform: translateY(-2px);
  border-color: rgba(59, 130, 246, 0.4);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}
.bento-action-icon {
  width: 46px; height: 46px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}
.bento-action-card.act-blue .bento-action-icon   { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
.bento-action-card.act-amber .bento-action-icon  { background: rgba(245,158,11,0.15); color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
.bento-action-card.act-cyan .bento-action-icon   { background: rgba(6,182,212,0.15); color: #38bdf8; border: 1px solid rgba(6,182,212,0.3); }
.bento-action-card.act-purple .bento-action-icon { background: rgba(139,92,246,0.15); color: #c084fc; border: 1px solid rgba(139,92,246,0.3); }
.bento-action-info b { display: block; font-size: 14px; font-weight: 800; color: #f1f5f9; margin-bottom: 2px; }
.bento-action-info small { font-size: 11.5px; color: #94a3b8; }

/* CHART & CALC */
.chart-calc-grid {
  display: grid;
  grid-template-columns: 1.25fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}
@media (max-width: 1024px) { .chart-calc-grid { grid-template-columns: 1fr; } }

.panel-card {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
}
.panel-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}
.panel-card-header h3 { font-size: 15px; font-weight: 800; color: #f1f5f9; margin: 0; }

.calc-types { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.calc-type-btn {
  padding: 12px;
  border-radius: 10px;
  background: #090d16;
  border: 2px solid rgba(255, 255, 255, 0.08);
  color: #94a3b8;
  font-weight: 700;
  font-size: 13px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.calc-type-btn.active {
  background: rgba(37, 99, 235, 0.15);
  border-color: #2563eb;
  color: #ffffff;
}
.calc-slider {
  width: 100%;
  height: 8px;
  border-radius: 4px;
  background: #1e293b;
  outline: none;
  -webkit-appearance: none;
  margin: 12px 0 6px;
}
.calc-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 20px; height: 20px;
  border-radius: 50%;
  background: #2563eb;
  cursor: pointer;
  border: 2px solid #ffffff;
}
.calc-result-box {
  background: #090d16;
  border: 1px solid rgba(37, 99, 235, 0.3);
  border-radius: 14px;
  padding: 18px;
  text-align: center;
  margin-top: 14px;
}
.calc-price {
  font-size: 32px;
  font-weight: 800;
  color: #38bdf8;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: -1px;
}

/* TABLE */
.dash-card-table {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  overflow: hidden;
  margin-bottom: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}
.dash-card-hdr {
  padding: 18px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.dash-card-hdr h3 { margin: 0; font-size: 15px; font-weight: 800; color: #f1f5f9; }
.dash-table-wrap { overflow-x: auto; }
.dash-table { width: 100%; border-collapse: collapse; font-size: 13.5px; min-width: 680px; }
.dash-table th {
  background: #090d16;
  padding: 14px 20px;
  text-align: left;
  font-size: 11px;
  font-weight: 800;
  color: #cbd5e1;
  text-transform: uppercase;
  letter-spacing: 0.7px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.dash-table td {
  padding: 14px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  color: #e2e8f0;
  vertical-align: middle;
}
.dash-table tbody tr:hover { background: rgba(59, 130, 246, 0.04); }
.dash-table tbody tr:last-child td { border-bottom: none; }

.plat-badge {
  background: #090d16;
  border: 1px solid #334155;
  color: #38bdf8;
  padding: 5px 12px;
  border-radius: 8px;
  font-weight: 800;
  letter-spacing: 1px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px;
  display: inline-block;
}
.jenis-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 8px;
}
.jenis-badge.motor { background: rgba(59,130,246,0.12); color: #60a5fa; }
.jenis-badge.mobil { background: rgba(16,185,129,0.12); color: #34d399; }

/* Custom Dark Pagination inside Dashboard Tables */
.dash-card-table .pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 24px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(9, 13, 22, 0.65);
  flex-wrap: wrap;
  gap: 12px;
  font-size: 13px;
  color: #94a3b8;
}
.dash-card-table .pagination-info b {
  color: #f1f5f9;
}
.dash-card-table .pagination-buttons {
  display: flex;
  gap: 6px;
  align-items: center;
}
.dash-card-table .page-btn {
  padding: 6px 14px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #cbd5e1;
  font-weight: 700;
  font-size: 12.5px;
  text-decoration: none;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.dash-card-table .page-btn:hover {
  background: #2563eb;
  border-color: #3b82f6;
  color: #ffffff;
  transform: translateY(-1px);
}
.dash-card-table .page-btn.active {
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
  color: #ffffff;
  border-color: #60a5fa;
  box-shadow: 0 0 12px rgba(37, 99, 235, 0.4);
}
.dash-card-table .page-btn.disabled {
  opacity: 0.35;
  pointer-events: none;
  background: rgba(255, 255, 255, 0.02);
  border-color: rgba(255, 255, 255, 0.06);
}

/* TRUST STRIP */
.trust-strip-dash {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 20px 24px;
  margin-top: 24px;
  display: flex;
  justify-content: space-around;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}
.trust-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  font-weight: 700;
  color: #cbd5e1;
}
</style>

<!-- 1. BANNER -->
<div class="dash-banner">
  <div class="dash-banner-content">
    <h2>
      <span>Selamat Datang, <?= e($user_name ?: ($_SESSION['nama_lengkap'] ?? 'User')) ?></span>
      <span class="grad-text">— <?= e($perusahaan['nama_perusahaan']) ?></span>
    </h2>
    <p>Console Operasional & Telemetri Smart Urban Mobility Bogor</p>
  </div>
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
    <div class="telemetry-pill">
      <span class="radar-pulse"></span>
      <span>99.98% Telemetry Active</span>
    </div>
    <a href="kiosk.php" target="_blank" class="btn btn-outline btn-sm" style="border-color:rgba(56,189,248,0.4);color:#38bdf8;">
      🖥️ Kiosk Mandiri
    </a>
  </div>
</div>

<!-- 2. KPI STATS GRID -->
<div class="stats-grid-enterprise">
  <div class="stat-card-ent blue">
    <div class="stat-card-flex">
      <div>
        <div class="stat-ent-label">Masuk Hari Ini</div>
        <div class="stat-ent-value cyan"><?= number_format($ring['masuk_hari_ini'] ?? 0) ?></div>
        <div class="stat-ent-desc">Kendaraan tercatat hari ini</div>
      </div>
      <div class="stat-card-icon blue">🚗</div>
    </div>
  </div>

  <div class="stat-card-ent amber">
    <div class="stat-card-flex">
      <div>
        <div class="stat-ent-label">Sedang Terparkir</div>
        <div class="stat-ent-value" style="color:#fbbf24;"><?= number_format($ring['masih_parkir'] ?? 0) ?></div>
        <div class="stat-ent-desc">Kendaraan aktif di area bay</div>
      </div>
      <div class="stat-card-icon amber">🅿️</div>
    </div>
  </div>

  <div class="stat-card-ent green">
    <div class="stat-card-flex">
      <div>
        <div class="stat-ent-label">Pendapatan Hari Ini</div>
        <div class="stat-ent-value green" style="font-size:24px;"><?= rupiah($ring['pendapatan_hari_ini'] ?? 0) ?></div>
        <div class="stat-ent-desc">Termasuk denda & cashless</div>
      </div>
      <div class="stat-card-icon green">💰</div>
    </div>
  </div>

  <div class="stat-card-ent cyan">
    <div class="stat-card-flex">
      <div>
        <div class="stat-ent-label">Ketersediaan Slot</div>
        <div class="stat-ent-value"><?= number_format($ring['slot_kosong'] ?? 0) ?> <span style="font-size:16px;color:#64748b;font-weight:600;">/ <?= number_format($ring['slot_total'] ?? 0) ?></span></div>
        <div class="stat-ent-desc"><?= $persen_tersedia ?>% Bay Kosong</div>
      </div>
      <div class="stat-card-icon cyan">📊</div>
    </div>
  </div>
</div>

<!-- 3. ANPR TERMINAL + OCCUPANCY -->
<div class="two-col-showcase">
  <div class="terminal-window">
    <div class="terminal-header">
      <div class="terminal-dots">
        <div class="terminal-dot red"></div>
        <div class="terminal-dot yellow"></div>
        <div class="terminal-dot green"></div>
      </div>
      <div class="terminal-title">GATE_01 // ANPR_BARRIER_FEED</div>
      <span class="badge badge-success" style="font-size:10px;padding:3px 8px;background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);">LIVE SCANNER</span>
    </div>
    <div class="terminal-body">
      <div class="anpr-scan-card">
        <div style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:11.5px;font-weight:700;">
          <span style="color:#38bdf8;">● OPTICAL PLATE RECOGNITION</span>
          <span style="color:#34d399;font-family:'JetBrains Mono';">CONFIDENCE: 99.4%</span>
        </div>
        <div class="plate-display" id="anprPlate">
          <?= e($latest_trx['plat_nomor'] ?? 'F 1428 KLR') ?>
        </div>
      </div>

      <div class="gate-info-row">
        <div class="gate-info-box">
          <small>Status Barrier Gate</small>
          <b style="color:#34d399;">GATE OPENED [AUTO]</b>
        </div>
        <div class="gate-info-box">
          <small>Tiket Digital QR</small>
          <b style="color:#38bdf8;"><?= e($latest_trx['kode_tiket'] ?? ('TKT-' . date('ymd') . '-882')) ?></b>
        </div>
        <div class="gate-info-box">
          <small>Alokasi Slot Parkir</small>
          <b style="color:#f59e0b;">ZONA <?= e($latest_trx['zona'] ?? 'A') ?> · BAY <?= e($latest_trx['kode_slot'] ?? 'C-04') ?></b>
        </div>
        <div class="gate-info-box">
          <small>Metode Pembayaran</small>
          <b style="color:#e2e8f0;">QRIS / CASHLESS READY</b>
        </div>
      </div>
    </div>
  </div>

  <div class="telemetry-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
      <div>
        <h3 style="font-size:17px;font-weight:800;color:#fff;margin:0 0 4px 0;">Telemetri Okupansi Bay</h3>
        <p style="color:#94a3b8;font-size:12.5px;margin:0;">Kapasitas Keseluruhan: <b><?= $ring['slot_kosong'] ?? 0 ?></b> Tersedia</p>
      </div>
      <span class="badge badge-success" style="padding:6px 14px;font-size:12px;background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);">
        <?= $persen_tersedia ?>% Slot Kosong
      </span>
    </div>

    <!-- Motor Slot Box -->
    <div class="telemetry-item">
      <div class="telemetry-header">
        <div>
          <span style="font-size:11.5px;color:#94a3b8;font-weight:700;text-transform:uppercase;">🏍️ Slot Motor (Zona A/B)</span>
          <div style="font-size:24px;font-weight:800;color:#fff;font-family:'JetBrains Mono';margin-top:2px;">
            <?= $slot_motor['kosong'] ?> <span style="font-size:14px;color:#64748b;">/ <?= $slot_motor['total'] ?> Tersedia</span>
          </div>
        </div>
        <span class="badge badge-primary" style="background:rgba(59,130,246,0.15);color:#60a5fa;border:1px solid rgba(59,130,246,0.3);">
          <?= $slot_motor['terisi'] ?> Terisi
        </span>
      </div>
      <div class="telemetry-progress-bg">
        <div class="telemetry-progress-fill" style="width:<?= $pct_motor ?>%; background: linear-gradient(90deg, #3b82f6, #60a5fa);"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;">
        <span>Okupansi: <b><?= $pct_motor ?>%</b></span>
        <span style="color:#38bdf8;">Tarif: Rp <?= number_format($tarifList['motor']['per_jam'], 0, ',', '.') ?>/jam</span>
      </div>
    </div>

    <!-- Mobil Slot Box -->
    <div class="telemetry-item" style="margin-bottom:0;">
      <div class="telemetry-header">
        <div>
          <span style="font-size:11.5px;color:#94a3b8;font-weight:700;text-transform:uppercase;">🚗 Slot Mobil (Zona C)</span>
          <div style="font-size:24px;font-weight:800;color:#fff;font-family:'JetBrains Mono';margin-top:2px;">
            <?= $slot_mobil['kosong'] ?> <span style="font-size:14px;color:#64748b;">/ <?= $slot_mobil['total'] ?> Tersedia</span>
          </div>
        </div>
        <span class="badge badge-success" style="background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);">
          <?= $slot_mobil['terisi'] ?> Terisi
        </span>
      </div>
      <div class="telemetry-progress-bg">
        <div class="telemetry-progress-fill" style="width:<?= $pct_mobil ?>%; background: linear-gradient(90deg, #10b981, #34d399);"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;">
        <span>Okupansi: <b><?= $pct_mobil ?>%</b></span>
        <span style="color:#34d399;">Tarif: Rp <?= number_format($tarifList['mobil']['per_jam'], 0, ',', '.') ?>/jam</span>
      </div>
    </div>
  </div>
</div>

<!-- 4. BENTO ACTIONS -->
<div class="bento-actions-grid">
  <a href="kendaraan_masuk.php" class="bento-action-card act-blue">
    <div class="bento-action-icon">🚗</div>
    <div class="bento-action-info">
      <b>Kendaraan Masuk</b>
      <small>Gate Masuk</small>
    </div>
  </a>

  <a href="kendaraan_keluar.php" class="bento-action-card act-amber">
    <div class="bento-action-icon">🚙</div>
    <div class="bento-action-info">
      <b>Kendaraan Keluar</b>
      <small>Gate Pembayaran</small>
    </div>
  </a>

  <a href="kiosk.php" target="_blank" class="bento-action-card act-cyan">
    <div class="bento-action-icon">🖥️</div>
    <div class="bento-action-info">
      <b>Buka Kiosk</b>
      <small>Tiket Mandiri</small>
    </div>
  </a>

  <?php if (is_admin()): ?>
    <a href="laporan.php" class="bento-action-card act-purple">
      <div class="bento-action-icon">📈</div>
      <div class="bento-action-info">
        <b>Lihat Laporan</b>
        <small>Analisis Pendapatan</small>
      </div>
    </a>
  <?php else: ?>
    <a href="operator/riwayat.php" class="bento-action-card act-purple">
      <div class="bento-action-icon">📜</div>
      <div class="bento-action-info">
        <b>Riwayat Transaksi</b>
        <small>Catatan Operasional</small>
      </div>
    </a>
  <?php endif; ?>
</div>

<!-- 5. CHART & KALKULATOR -->
<div class="chart-calc-grid">
  <div class="panel-card">
    <div class="panel-card-header">
      <h3>📈 Pendapatan 7 Hari Terakhir</h3>
      <span class="badge badge-info" style="font-size:11px;background:rgba(56,189,248,0.12);color:#38bdf8;border:1px solid rgba(56,189,248,0.25);">
        Live Analytics
      </span>
    </div>
    <div style="position:relative;height:220px;">
      <canvas id="chartPendapatan"></canvas>
    </div>
  </div>

  <div class="panel-card">
    <div class="panel-card-header">
      <h3>⚙️ Kalkulator Estimasi Tarif</h3>
      <small style="color:#94a3b8;font-size:12px;">Simulasi Cepat Biaya</small>
    </div>

    <div class="calc-types">
      <button type="button" class="calc-type-btn active" id="btnCalcMobil" onclick="selectCalcType('mobil')">
        🚗 Mobil Pribadi
      </button>
      <button type="button" class="calc-type-btn" id="btnCalcMotor" onclick="selectCalcType('motor')">
        🏍️ Sepeda Motor
      </button>
    </div>

    <div>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;font-weight:700;">
        <span>DURASI PARKIR:</span>
        <span id="calcDurationLabel" style="color:#38bdf8;font-family:'JetBrains Mono';">2 Jam</span>
      </div>
      <input type="range" min="1" max="24" value="2" class="calc-slider" id="calcSlider" oninput="updateCalcPrice()">
      <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;font-family:'JetBrains Mono';">
        <span>1j</span>
        <span>6j</span>
        <span>12j</span>
        <span>24j</span>
      </div>
    </div>

    <div class="calc-result-box">
      <div style="font-size:11px;color:#94a3b8;font-weight:800;letter-spacing:0.8px;text-transform:uppercase;">Estimasi Biaya:</div>
      <div class="calc-price" id="calcResultPrice">Rp 10.000</div>
    </div>
  </div>
</div>

<!-- 6. KENDARAAN SEDANG PARKIR (DENGAN PAGINATION) -->
<div id="tabel-aktif" class="dash-card-table">
  <div class="dash-card-hdr">
    <h3>🚙 Kendaraan Sedang Parkir</h3>
    <a href="kendaraan_keluar.php" class="btn btn-sm btn-primary">Kelola Gerbang →</a>
  </div>
  <div class="dash-table-wrap">
    <table class="dash-table">
      <thead>
        <tr>
          <th>Kode Tiket</th>
          <th>Plat Nomor</th>
          <th>Jenis</th>
          <th>Slot</th>
          <th>Waktu Masuk</th>
          <th>Durasi</th>
          <th>Operator</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$aktif || $aktif->num_rows === 0): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:#64748b;">🅿️ Belum ada kendaraan parkir aktif</td></tr>
        <?php else: while ($r = $aktif->fetch_assoc()):
          $detik = time() - strtotime($r['waktu_masuk']);
          $jam = floor($detik / 3600);
          $menit = floor(($detik % 3600) / 60);
        ?>
          <tr>
            <td>
              <a href="struk.php?id=<?= $r['id_transaksi'] ?>" style="color:#38bdf8;font-family:'JetBrains Mono',monospace;font-size:12.5px;font-weight:700;text-decoration:none;">
                🎫 <?= e($r['kode_tiket']) ?>
              </a>
            </td>
            <td><span class="plat-badge"><?= e($r['plat_nomor']) ?></span></td>
            <td>
              <span class="jenis-badge <?= $r['jenis_kendaraan'] === 'motor' ? 'motor' : 'mobil' ?>">
                <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?>
              </span>
            </td>
            <td><b style="color:#f1f5f9;"><?= e($r['kode_slot']) ?></b></td>
            <td><?= date('H:i:s', strtotime($r['waktu_masuk'])) ?></td>
            <td><span style="font-family:'JetBrains Mono',monospace;font-weight:700;"><?= $jam ?>j <?= $menit ?>m</span></td>
            <td><?= e($r['operator'] ?? ($r['via_kiosk'] ? 'Kiosk' : '-')) ?></td>
            <td>
              <a href="struk.php?id=<?= $r['id_transaksi'] ?>" class="btn btn-sm btn-outline" style="border-color:rgba(56,189,248,0.4);color:#38bdf8;padding:4px 10px;font-size:11.5px;">
                🖨️ Struk
              </a>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pagination($pagi_aktif, [], '#tabel-aktif') ?>
</div>

<!-- 7. TRUST STRIP -->
<div class="trust-strip-dash">
  <div class="trust-item">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
    <span>ISO 27001 Certified Security</span>
  </div>
  <div class="trust-item">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
    <span>99.98% High-Availability Uptime</span>
  </div>
  <div class="trust-item">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
    <span>QRIS & Bank Indonesia Gateway</span>
  </div>
  <div class="trust-item">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
    <span>Sub-3-Second Gate Latency</span>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ctxChart = document.getElementById('chartPendapatan');
if (ctxChart) {
  new Chart(ctxChart, {
    type: 'line',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: [{
        label: 'Pendapatan',
        data: <?= json_encode($values) ?>,
        borderColor: '#38bdf8',
        backgroundColor: 'rgba(56, 189, 248, 0.12)',
        fill: true,
        tension: 0.4,
        borderWidth: 3,
        pointRadius: 4,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: '#0284c7',
        pointBorderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0f172a',
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 8,
          callbacks: { label: ctx => 'Rp ' + Number(ctx.parsed.y).toLocaleString('id-ID') }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(255,255,255,0.05)' },
          ticks: { color: '#94a3b8', font: { family: 'JetBrains Mono', size: 11 }, callback: v => 'Rp ' + Number(v).toLocaleString('id-ID') }
        },
        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' } } }
      }
    }
  });
}

// Data Tarif Kalkulator
const TARIF_DATA = <?= json_encode($tarifList) ?>;
let selectedVehicle = 'mobil';

function selectCalcType(type) {
  selectedVehicle = type;
  document.getElementById('btnCalcMobil').classList.toggle('active', type === 'mobil');
  document.getElementById('btnCalcMotor').classList.toggle('active', type === 'motor');
  updateCalcPrice();
}

function updateCalcPrice() {
  const slider = document.getElementById('calcSlider');
  const label = document.getElementById('calcDurationLabel');
  const res = document.getElementById('calcResultPrice');
  if (!slider || !label || !res) return;

  const hours = parseInt(slider.value, 10);
  label.textContent = hours + ' Jam';

  const cfg = TARIF_DATA[selectedVehicle] || { per_jam: 5000, minimal: 5000, maksimal: 50000 };
  let total = cfg.per_jam * hours;
  if (cfg.minimal && total < cfg.minimal) total = cfg.minimal;
  if (cfg.maksimal && total > cfg.maksimal) total = cfg.maksimal;

  res.textContent = 'Rp ' + Number(total).toLocaleString('id-ID');
}
updateCalcPrice();

// Simulated ANPR Plate Rotation
const samplePlates = ['<?= e($latest_trx['plat_nomor'] ?? 'F 1428 KLR') ?>', 'B 8920 PQR', 'D 4519 ZXA', 'B 3021 VBL', 'F 7721 NXZ'];
let plateIndex = 0;
setInterval(function() {
  plateIndex = (plateIndex + 1) % samplePlates.length;
  const plateEl = document.getElementById('anprPlate');
  if (plateEl) {
    plateEl.style.opacity = '0';
    setTimeout(function() {
      plateEl.textContent = samplePlates[plateIndex];
      plateEl.style.opacity = '1';
    }, 200);
  }
}, 4500);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
