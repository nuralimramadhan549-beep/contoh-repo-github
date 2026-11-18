<?php
// ============================================================
// DASHBOARD PELANGGAN - PT. PARKIR BOGOR (ENTERPRISE EDITION)
// Standard: Modern & Responsive Customer Self-Service Portal
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Pastikan hanya pelanggan yang bisa mengakses portal ini
cek_pelanggan();

$page_title = 'Dashboard Saya';
$page_subtitle = 'Ringkasan aktivitas kendaraan dan parkir Anda';

$uid = user_id();
$user_name = user_name();

// ============================================================
// 1. STATISTIK KENDARAAN & TRANSAKSI PELANGGAN
// ============================================================
$total_kendaraan = 0;
$total_transaksi = 0;
$total_bayar     = 0;

try {
    // Total kendaraan terdaftar
    $stmt = $koneksi->prepare("SELECT COUNT(*) AS total FROM kendaraan_pelanggan WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $total_kendaraan = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }
} catch (Exception $e) {}

try {
    // Total riwayat transaksi
    $stmt = $koneksi->prepare("SELECT COUNT(*) AS total FROM transaksi_parkir WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $total_transaksi = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }

    // Total pengeluaran parkir
    $stmt = $koneksi->prepare("
        SELECT COALESCE(SUM(p.total_biaya + IFNULL(p.denda, 0)), 0) AS total 
        FROM pembayaran p 
        JOIN transaksi_parkir t ON p.id_transaksi = t.id_transaksi 
        WHERE t.user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $total_bayar = (float)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    }
} catch (Exception $e) {}

// ============================================================
// 2. KENDARAAN SEDANG PARKIR (AKTIF)
// ============================================================
$kendaraan_aktif = [];
try {
    $stmt = $koneksi->prepare("
        SELECT t.*, s.kode_slot, s.zona 
        FROM transaksi_parkir t 
        JOIN slot_parkir s ON t.id_slot = s.id_slot 
        WHERE t.user_id = ? AND t.waktu_keluar IS NULL AND t.status IN ('aktif', 'menunggu_approval')
        ORDER BY t.waktu_masuk DESC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            // Hitung perkiraan biaya real-time
            $row['biaya_est'] = hitung_biaya($koneksi, $row['jenis_kendaraan'], $row['waktu_masuk']);
            $kendaraan_aktif[] = $row;
        }
    }
} catch (Exception $e) {}

$jumlah_aktif = count($kendaraan_aktif);

// ============================================================
// 3. RIWAYAT TRANSAKSI PELANGGAN (DENGAN PAGINATION)
// ============================================================
$total_pelanggan = 0;
try {
    $stmtTot = $koneksi->prepare("SELECT COUNT(*) as c FROM transaksi_parkir WHERE user_id = ?");
    if ($stmtTot) {
        $stmtTot->bind_param('i', $uid);
        $stmtTot->execute();
        $total_pelanggan = (int)$stmtTot->get_result()->fetch_assoc()['c'];
    }
} catch (Exception $e) {}
$pagi_pelanggan = paginate($total_pelanggan, 5, 'page');

$riwayat_pelanggan = [];
try {
    $stmt = $koneksi->prepare("
        SELECT t.*, s.kode_slot, s.zona, p.total_biaya, p.denda, p.metode_pembayaran 
        FROM transaksi_parkir t 
        JOIN slot_parkir s ON t.id_slot = s.id_slot 
        LEFT JOIN pembayaran p ON t.id_transaksi = p.id_transaksi 
        WHERE t.user_id = ? 
        ORDER BY t.id_transaksi DESC 
        LIMIT ? OFFSET ?
    ");
    if ($stmt) {
        $stmt->bind_param('iii', $uid, $pagi_pelanggan['per_page'], $pagi_pelanggan['offset']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $riwayat_pelanggan[] = $row;
        }
    }
} catch (Exception $e) {}
if ($total_pelanggan === 0 && !empty($riwayat_pelanggan)) {
    $total_pelanggan = count($riwayat_pelanggan);
    $pagi_pelanggan = paginate($total_pelanggan, 5, 'page');
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ============================================================
   DASHBOARD PELANGGAN - MODERN DARK THEME (TEAL)
   ============================================================ */

/* WELCOME BANNER */
.db-welcome {
  position: relative;
  background: linear-gradient(135deg, #0f766e 0%, #0d9488 40%, #14b8a6 100%);
  border: 1px solid rgba(20, 184, 166, 0.4);
  border-radius: 16px;
  padding: 28px 32px;
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  box-shadow: 0 8px 32px rgba(13, 148, 136, 0.3), inset 0 1px 0 rgba(255,255,255,0.1);
  overflow: hidden;
}
.db-welcome::before {
  content: '';
  position: absolute;
  top: -80px; right: -80px;
  width: 220px; height: 220px;
  background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.db-welcome::after {
  content: '';
  position: absolute;
  bottom: -50px; left: 30%;
  width: 160px; height: 160px;
  background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.db-welcome-info { position: relative; z-index: 1; }
.db-welcome-info h2 {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 6px 0;
  letter-spacing: -0.3px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.db-welcome-info p {
  color: #ccfbf1;
  margin: 0;
  font-size: 13.5px;
  font-weight: 500;
}
.db-date-pill {
  position: relative;
  z-index: 1;
  padding: 10px 18px;
  background: rgba(0, 0, 0, 0.25);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 12px;
  color: #ffffff;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* KPI STAT CARDS */
.db-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 24px;
}
.db-stat-card {
  background: #111827;
  border-radius: 14px;
  padding: 22px 24px;
  border: 1px solid #1e293b;
  position: relative;
  overflow: hidden;
  transition: all 0.25s ease;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.db-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  border-color: #334155;
}
.db-stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  border-radius: 14px 14px 0 0;
}
.db-stat-card.teal::before   { background: linear-gradient(90deg, #0d9488, #14b8a6); }
.db-stat-card.amber::before  { background: linear-gradient(90deg, #d97706, #f59e0b); }
.db-stat-card.cyan::before   { background: linear-gradient(90deg, #0891b2, #06b6d4); }
.db-stat-card.emerald::before{ background: linear-gradient(90deg, #059669, #10b981); }

.db-stat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}
.db-stat-label {
  font-size: 11.5px;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.6px;
}
.db-stat-icon {
  width: 40px; height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.db-stat-icon.teal   { background: rgba(13,148,136,0.15); color: #14b8a6; }
.db-stat-icon.amber  { background: rgba(245,158,11,0.15); color: #f59e0b; }
.db-stat-icon.cyan   { background: rgba(6,182,212,0.15);  color: #06b6d4; }
.db-stat-icon.emerald{ background: rgba(16,185,129,0.15); color: #10b981; }

.db-stat-value {
  font-size: 28px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1.1;
  margin-bottom: 4px;
  letter-spacing: -0.5px;
}
.db-stat-value.sm { font-size: 22px; }
.db-stat-desc {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

/* QUICK ACTIONS */
.db-actions-panel {
  background: #111827;
  border: 1px solid #1e293b;
  border-radius: 16px;
  padding: 22px 24px;
  margin-bottom: 24px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.db-section-title {
  font-size: 14px;
  font-weight: 800;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.db-section-title svg { opacity: 0.7; }
.db-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 12px;
}
.db-action-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 12px;
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.25s ease;
  border: 1px solid #1e293b;
  background: rgba(255, 255, 255, 0.02);
  color: #cbd5e1;
}
.db-action-btn:hover {
  background: rgba(13, 148, 136, 0.12);
  border-color: rgba(13, 148, 136, 0.4);
  color: #5eead4;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(13,148,136,0.15);
}
.db-action-btn.primary {
  background: rgba(13, 148, 136, 0.15);
  border-color: rgba(13, 148, 136, 0.4);
  color: #5eead4;
}
.db-action-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255,255,255,0.05);
  flex-shrink: 0;
}
.db-action-btn:hover .db-action-icon {
  background: rgba(13, 148, 136, 0.2);
}

/* ACTIVE PARKING SECTION */
.db-active-section {
  background: #111827;
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 4px 20px rgba(245, 158, 11, 0.06);
}
.db-active-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
  flex-wrap: wrap;
  gap: 12px;
}
.db-active-header h3 {
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.db-active-badge {
  padding: 5px 14px;
  border-radius: 9999px;
  font-size: 11.5px;
  font-weight: 800;
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.35);
  display: flex;
  align-items: center;
  gap: 6px;
}
.db-active-badge .pulse-dot {
  width: 7px; height: 7px;
  background: #f59e0b;
  border-radius: 50%;
  animation: pulse-glow 2s infinite;
}
@keyframes pulse-glow {
  0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(245,158,11,0.5); }
  50% { opacity: 0.7; box-shadow: 0 0 0 6px rgba(245,158,11,0); }
}
.db-vehicle-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid #1e293b;
  border-radius: 14px;
  padding: 20px 22px;
  margin-bottom: 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
  transition: all 0.25s ease;
}
.db-vehicle-card:last-child { margin-bottom: 0; }
.db-vehicle-card:hover {
  border-color: rgba(245, 158, 11, 0.35);
  background: rgba(245, 158, 11, 0.03);
}
.db-plate {
  font-size: 18px;
  font-weight: 800;
  color: #ffffff;
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  letter-spacing: 1.5px;
  background: rgba(255,255,255,0.05);
  padding: 4px 12px;
  border-radius: 6px;
  border: 1px solid #334155;
}
.db-vehicle-type {
  font-size: 12px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.db-vehicle-type.motor { background: rgba(96,165,250,0.12); color: #60a5fa; border: 1px solid rgba(96,165,250,0.25); }
.db-vehicle-type.mobil { background: rgba(52,211,153,0.12); color: #34d399; border: 1px solid rgba(52,211,153,0.25); }
.db-slot-tag {
  font-size: 11.5px;
  background: #1e293b;
  color: #f59e0b;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid rgba(245,158,11,0.25);
  font-weight: 700;
}
.db-meta-row {
  display: flex;
  gap: 18px;
  flex-wrap: wrap;
  margin-top: 10px;
}
.db-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12.5px;
  color: #94a3b8;
}
.db-meta-item b { color: #e2e8f0; }
.db-meta-item .val-highlight { color: #34d399; font-weight: 700; }
.db-meta-item .val-code { font-family: 'JetBrains Mono', monospace; color: #38bdf8; font-weight: 700; }
.db-checkout-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #d97706, #f59e0b);
  color: #000;
  padding: 11px 20px;
  border-radius: 10px;
  font-weight: 800;
  font-size: 13px;
  text-decoration: none;
  box-shadow: 0 4px 14px rgba(245,158,11,0.3);
  transition: all 0.25s ease;
  border: none;
}
.db-checkout-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(245,158,11,0.4);
  color: #000;
}

/* TRANSACTION TABLE */
.db-table-card {
  background: #111827;
  border-radius: 16px;
  border: 1px solid #1e293b;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
  margin-bottom: 24px;
}
.db-table-header {
  padding: 18px 24px;
  border-bottom: 1px solid #1e293b;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.db-table-header h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 10px;
}
.db-table-link {
  color: #14b8a6;
  font-size: 13px;
  font-weight: 700;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: color 0.2s;
}
.db-table-link:hover { color: #5eead4; }
.db-table-scroll { overflow-x: auto; }
table.db-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13.5px;
  min-width: 680px;
}
table.db-table th {
  background: #0a0f1a;
  padding: 14px 20px;
  text-align: left;
  font-size: 11px;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  border-bottom: 1px solid #1e293b;
  letter-spacing: 0.6px;
}
table.db-table td {
  padding: 14px 20px;
  border-bottom: 1px solid rgba(30,41,59,0.6);
  color: #e2e8f0;
  vertical-align: middle;
}
table.db-table tbody tr { transition: background 0.15s; }
table.db-table tbody tr:hover { background: rgba(255, 255, 255, 0.02); }
table.db-table tbody tr:last-child td { border-bottom: none; }

/* Custom Dark Pagination inside Pelanggan Dashboard Table */
.db-table-card .pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 24px;
  border-top: 1px solid #1e293b;
  background: rgba(9, 13, 22, 0.65);
  flex-wrap: wrap;
  gap: 12px;
  font-size: 13px;
  color: #94a3b8;
}
.db-table-card .pagination-info b {
  color: #f1f5f9;
}
.db-table-card .pagination-buttons {
  display: flex;
  gap: 6px;
  align-items: center;
}
.db-table-card .page-btn {
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
.db-table-card .page-btn:hover {
  background: #0d9488;
  border-color: #14b8a6;
  color: #ffffff;
  transform: translateY(-1px);
}
.db-table-card .page-btn.active {
  background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
  color: #ffffff;
  border-color: #2dd4bf;
  box-shadow: 0 0 12px rgba(20, 184, 166, 0.4);
}
.db-table-card .page-btn.disabled {
  opacity: 0.35;
  pointer-events: none;
  background: rgba(255, 255, 255, 0.02);
  border-color: rgba(255, 255, 255, 0.06);
}

.db-plate-badge {
  background: #1e293b;
  border: 1px solid #334155;
  color: #38bdf8;
  padding: 4px 10px;
  border-radius: 6px;
  font-weight: 800;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12.5px;
  display: inline-block;
}
.db-status-pill {
  padding: 4px 12px;
  border-radius: 9999px;
  font-size: 11px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
.db-status-pill.aktif {
  background: rgba(245,158,11,0.12);
  color: #fbbf24;
  border: 1px solid rgba(245,158,11,0.3);
}
.db-status-pill.menunggu {
  background: rgba(6,182,212,0.12);
  color: #22d3ee;
  border: 1px solid rgba(6,182,212,0.3);
}
.db-status-pill.selesai {
  background: rgba(16,185,129,0.12);
  color: #34d399;
  border: 1px solid rgba(16,185,129,0.3);
}
.db-status-pill.default {
  background: rgba(148,163,184,0.12);
  color: #94a3b8;
  border: 1px solid rgba(148,163,184,0.3);
}

/* TIP BOX */
.db-tip-box {
  background: rgba(13, 148, 136, 0.06);
  border: 1px solid rgba(13, 148, 136, 0.2);
  border-left: 4px solid #0d9488;
  border-radius: 12px;
  padding: 18px 22px;
  display: flex;
  align-items: flex-start;
  gap: 14px;
  font-size: 13.5px;
  color: #99f6e4;
  line-height: 1.7;
}
.db-tip-box a {
  color: #5eead4;
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 2px;
}
.db-tip-box a:hover { color: #ffffff; }
.db-tip-icon {
  flex-shrink: 0;
  width: 38px; height: 38px;
  border-radius: 10px;
  background: rgba(13,148,136,0.15);
  display: flex;
  align-items: center;
  justify-content: center;
}

/* EMPTY STATE */
.db-empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #64748b;
}
.db-empty-state svg { margin-bottom: 12px; opacity: 0.4; }
.db-empty-state a { color: #14b8a6; font-weight: 700; }

/* RESPONSIVE (MOBILE SIMULATOR & ALL VIEWPORTS) */
@media (max-width: 1024px) {
  .db-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
  .db-welcome { padding: 20px 18px; margin-bottom: 16px; }
  .db-welcome-info h2 { font-size: 19px; }
  .db-stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
  .db-stat-card { padding: 16px 14px; }
  .db-stat-value { font-size: 24px; }
  .db-actions-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
  .db-vehicle-card { flex-direction: column; align-items: flex-start; }
  .db-checkout-btn { width: 100%; justify-content: center; }
  .db-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
}
@media (max-width: 640px) {
  .db-welcome {
    padding: 16px 14px;
    border-radius: 14px;
    gap: 12px;
    margin-bottom: 14px;
  }
  .db-welcome-info h2 { font-size: 17px; gap: 8px; }
  .db-welcome-info p { font-size: 12px; }
  .db-date-pill { padding: 6px 12px; font-size: 11.5px; }

  .db-stats-grid {
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 14px;
  }
  .db-stat-card { padding: 14px 12px; border-radius: 12px; }
  .db-stat-header { margin-bottom: 10px; }
  .db-stat-label { font-size: 10px; }
  .db-stat-icon { width: 32px; height: 32px; border-radius: 8px; }
  .db-stat-icon svg { width: 16px; height: 16px; }
  .db-stat-value { font-size: 20px; }
  .db-stat-desc { font-size: 11px; }

  .db-actions-panel {
    padding: 16px 14px;
    border-radius: 14px;
    margin-bottom: 14px;
  }
  .db-section-title { font-size: 13px; margin-bottom: 12px; }
  .db-actions-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
  .db-action-btn { padding: 10px 10px; font-size: 12px; gap: 8px; border-radius: 10px; }
  .db-action-icon { width: 28px; height: 28px; }
  .db-action-icon svg { width: 15px; height: 15px; }

  .db-active-section {
    padding: 16px 14px;
    border-radius: 14px;
    margin-bottom: 14px;
  }
  .db-active-header { margin-bottom: 12px; gap: 8px; }
  .db-active-header h3 { font-size: 14px; }
  .db-active-badge { font-size: 11px; padding: 4px 10px; }
  .db-vehicle-card { padding: 14px 12px; border-radius: 12px; gap: 12px; }
  .db-plate { font-size: 16px; letter-spacing: 1.5px; padding: 4px 10px; }
  .db-meta-row { gap: 10px; margin-top: 8px; }
  .db-meta-item { font-size: 11.5px; }

  .db-table-card { border-radius: 14px; margin-bottom: 14px; }
  .db-table-header { padding: 14px 14px; flex-wrap: wrap; gap: 8px; }
  .db-table-header h3 { font-size: 14px; }
  table.db-table th, table.db-table td { padding: 10px 12px; font-size: 12px; }
  .db-plate-badge { font-size: 11.5px; padding: 3px 8px; }

  .db-table-card .pagination {
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 14px;
    text-align: center;
  }
  .db-table-card .pagination-buttons {
    flex-wrap: wrap;
    justify-content: center;
    gap: 4px;
  }
  .db-table-card .page-btn {
    padding: 5px 10px;
    font-size: 11.5px;
    min-width: 32px;
    min-height: 32px;
  }

  .db-tip-box {
    padding: 14px 12px;
    font-size: 12px;
    gap: 10px;
    border-radius: 12px;
  }
  .db-tip-icon { width: 32px; height: 32px; }
}
@media (max-width: 380px) {
  .db-stats-grid { grid-template-columns: 1fr; }
  .db-actions-grid { grid-template-columns: 1fr; }
  .db-welcome { flex-direction: column; align-items: flex-start; }
  .db-date-pill { align-self: flex-start; }
}
</style>

<!-- ======================= WELCOME BANNER ======================= -->
<div class="db-welcome">
  <div class="db-welcome-info">
    <h2>
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Halo, <?= e($_SESSION['nama_lengkap']) ?>!
    </h2>
    <p>Portal Layanan Mandiri Pelanggan · <?= e($perusahaan['nama_perusahaan']) ?></p>
  </div>
  <div class="db-date-pill">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    <?= tgl_indo(date('Y-m-d')) ?>
  </div>
</div>

<!-- ======================= KPI STATS ======================= -->
<div class="db-stats-grid">
  <!-- Kendaraan Saya -->
  <div class="db-stat-card teal">
    <div class="db-stat-header">
      <span class="db-stat-label">Kendaraan Saya</span>
      <div class="db-stat-icon teal">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
      </div>
    </div>
    <div class="db-stat-value"><?= number_format($total_kendaraan) ?></div>
    <div class="db-stat-desc">Terdaftar di akun Anda</div>
  </div>

  <!-- Sedang Parkir -->
  <div class="db-stat-card amber">
    <div class="db-stat-header">
      <span class="db-stat-label">Sedang Parkir</span>
      <div class="db-stat-icon amber">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>
      </div>
    </div>
    <div class="db-stat-value"><?= number_format($jumlah_aktif) ?></div>
    <div class="db-stat-desc"><?= $jumlah_aktif > 0 ? 'Kendaraan aktif di area' : 'Tidak ada kendaraan aktif' ?></div>
  </div>

  <!-- Total Transaksi -->
  <div class="db-stat-card cyan">
    <div class="db-stat-header">
      <span class="db-stat-label">Total Transaksi</span>
      <div class="db-stat-icon cyan">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      </div>
    </div>
    <div class="db-stat-value"><?= number_format($total_transaksi) ?></div>
    <div class="db-stat-desc">Riwayat kedatangan</div>
  </div>

  <!-- Total Pengeluaran -->
  <div class="db-stat-card emerald">
    <div class="db-stat-header">
      <span class="db-stat-label">Total Pengeluaran</span>
      <div class="db-stat-icon emerald">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      </div>
    </div>
    <div class="db-stat-value sm"><?= rupiah($total_bayar) ?></div>
    <div class="db-stat-desc">Akumulasi pembayaran parkir</div>
  </div>
</div>

<!-- ======================= QUICK ACTIONS ======================= -->
<div class="db-actions-panel">
  <div class="db-section-title">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#14b8a6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
    Menu Cepat Pelanggan
  </div>
  <div class="db-actions-grid">
    <a href="<?= PATH_PUBLIC ?>kendaraan_saya.php" class="db-action-btn primary">
      <div class="db-action-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
      </div>
      <span>Daftar Kendaraan</span>
    </a>
    <a href="<?= PATH_PUBLIC ?>masuk.php" class="db-action-btn">
      <div class="db-action-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>
      </div>
      <span>Parkir Sekarang</span>
    </a>
    <a href="<?= PATH_PUBLIC ?>keluar.php" class="db-action-btn">
      <div class="db-action-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      </div>
      <span>Bayar & Keluar</span>
    </a>
    <a href="<?= PATH_PUBLIC ?>riwayat.php" class="db-action-btn">
      <div class="db-action-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <span>Riwayat Transaksi</span>
    </a>
    <a href="<?= BASE_URL ?>kiosk.php" target="_blank" class="db-action-btn">
      <div class="db-action-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      </div>
      <span>Terminal Kiosk Mandiri</span>
    </a>
  </div>
</div>

<!-- ======================= KENDARAAN SEDANG PARKIR ======================= -->
<?php if ($jumlah_aktif > 0): ?>
  <div class="db-active-section">
    <div class="db-active-header">
      <h3>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>
        Kendaraan Anda yang Sedang Parkir
      </h3>
      <span class="db-active-badge">
        <span class="pulse-dot"></span>
        <?= $jumlah_aktif ?> Kendaraan Aktif
      </span>
    </div>

    <?php foreach ($kendaraan_aktif as $ka): ?>
      <div class="db-vehicle-card">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
            <span class="db-plate"><?= e($ka['plat_nomor']) ?></span>
            <span class="db-vehicle-type <?= $ka['jenis_kendaraan'] === 'motor' ? 'motor' : 'mobil' ?>">
              <?php if ($ka['jenis_kendaraan'] === 'motor'): ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><path d="M15 6a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" fill="currentColor"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>
                Sepeda Motor
              <?php else: ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
                Mobil Pribadi
              <?php endif; ?>
            </span>
            <span class="db-slot-tag">
              Slot: <b><?= e($ka['kode_slot']) ?></b> (Zona <?= e($ka['zona'] ?? 'A') ?>)
            </span>
          </div>

          <div class="db-meta-row">
            <span class="db-meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              Masuk: <b><?= date('H:i', strtotime($ka['waktu_masuk'])) ?> (<?= format_durasi($ka['waktu_masuk']) ?> berjalan)</b>
            </span>
            <span class="db-meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 10 3 12 0v-5"/></svg>
              Tiket: <b class="val-code"><?= e($ka['kode_tiket']) ?></b>
            </span>
            <?php if (isset($ka['biaya_est']['total'])): ?>
              <span class="db-meta-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                Est. Biaya: <b class="val-highlight"><?= rupiah($ka['biaya_est']['total']) ?></b>
              </span>
            <?php endif; ?>
          </div>
        </div>

        <div>
          <a href="<?= PATH_PUBLIC ?>keluar.php?id=<?= (int)$ka['id_transaksi'] ?>" class="db-checkout-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Bayar & Checkout
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ======================= RIWAYAT TERAKHIR (DENGAN PAGINATION) ======================= -->
<div id="tabel-riwayat" class="db-table-card">
  <div class="db-table-header">
    <h3>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#14b8a6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Riwayat Transaksi Terakhir
    </h3>
    <a href="<?= PATH_PUBLIC ?>riwayat.php" class="db-table-link">
      Lihat Semua (<?= $total_pelanggan ?>)
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>

  <div class="db-table-scroll">
    <table class="db-table">
      <thead>
        <tr>
          <th>Tanggal & Jam</th>
          <th>Plat Nomor</th>
          <th>Slot</th>
          <th>Durasi</th>
          <th>Total Biaya</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($riwayat_pelanggan)): ?>
          <tr>
            <td colspan="6">
              <div class="db-empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <div>Belum ada riwayat transaksi.</div>
                <div style="margin-top:6px;"><a href="<?= PATH_PUBLIC ?>masuk.php">Parkir sekarang</a> untuk memulai.</div>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($riwayat_pelanggan as $tr): ?>
            <tr>
              <td>
                <b style="color:#ffffff;"><?= date('H:i', strtotime($tr['waktu_masuk'])) ?></b>
                <div style="font-size:11px;color:#64748b;margin-top:2px;"><?= date('d/m/Y', strtotime($tr['waktu_masuk'])) ?></div>
              </td>
              <td>
                <span class="db-plate-badge"><?= e($tr['plat_nomor']) ?></span>
              </td>
              <td>
                <b><?= e($tr['kode_slot']) ?></b>
                <span style="font-size:11px;color:#64748b;"> (<?= e($tr['zona'] ?? 'A') ?>)</span>
              </td>
              <td>
                <?php if ($tr['waktu_keluar']): ?>
                  <b><?= (int)$tr['durasi_jam'] ?> jam</b>
                <?php else: ?>
                  <span style="color:#f59e0b;font-weight:600;display:inline-flex;align-items:center;gap:4px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= format_durasi($tr['waktu_masuk']) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($tr['status'] === 'selesai'): ?>
                  <b style="color:#34d399;"><?= rupiah(($tr['total_biaya'] ?? 0) + ($tr['denda'] ?? 0)) ?></b>
                <?php else: ?>
                  <span style="color:#64748b;">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($tr['status'] === 'aktif'): ?>
                  <span class="db-status-pill aktif">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9" stroke="#000" stroke-width="2" fill="none"/></svg>
                    Parkir
                  </span>
                <?php elseif ($tr['status'] === 'menunggu_approval'): ?>
                  <span class="db-status-pill menunggu">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Menunggu
                  </span>
                <?php elseif ($tr['status'] === 'selesai'): ?>
                  <span class="db-status-pill selesai">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                    Selesai
                  </span>
                <?php else: ?>
                  <span class="db-status-pill default"><?= e($tr['status']) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pagination($pagi_pelanggan, [], '#tabel-riwayat') ?>
</div>

<!-- ======================= INFORMASI / TIPS ======================= -->
<div class="db-tip-box">
  <div class="db-tip-icon">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#14b8a6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  </div>
  <div>
    <b style="color:#ffffff;">Kemudahan Parkir Cepat:</b> Daftarkan plat nomor kendaraan Anda di menu 
    <a href="<?= PATH_PUBLIC ?>kendaraan_saya.php">Kendaraan Saya</a> agar proses check-in dan check-out berlangsung otomatis. Anda juga dapat menggunakan tiket mandiri pada <a href="<?= BASE_URL ?>kiosk.php" target="_blank">Terminal Kiosk Layar Sentuh</a>.
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
