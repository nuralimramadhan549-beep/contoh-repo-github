<?php
// ============================================================
// ENTERPRISE LANDING PAGE - PT. PARKIR BOGOR
// Standard: Modern & International Smart Mobility Platform
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$sudahLogin = is_login();
$dash_url = $sudahLogin ? dashboard_url() : BASE_URL . 'login.php';
$dash_label = $sudahLogin ? 'Dashboard Saya' : 'Login Portals';

// Statistik Live dari Database
$statSlotKosong = $statSlotTotal = $statKendaraan = $statTransaksi = 0;
$statSlotMotorKosong = $statSlotMotorTotal = 0;
$statSlotMobilKosong = $statSlotMobilTotal = 0;
$tarifList = [];

try {
    $statSlotKosong = (int)$koneksi->query("SELECT COUNT(*) t FROM slot_parkir WHERE status='kosong'")->fetch_assoc()['t'];
    $statSlotTotal  = (int)$koneksi->query("SELECT COUNT(*) t FROM slot_parkir")->fetch_assoc()['t'];
    $statKendaraan  = (int)$koneksi->query("SELECT COUNT(DISTINCT plat_nomor) t FROM transaksi_parkir")->fetch_assoc()['t'];
    $statTransaksi  = (int)$koneksi->query("SELECT COUNT(*) t FROM transaksi_parkir WHERE status='selesai'")->fetch_assoc()['t'];

    // Breakdown per jenis kendaraan
    $rMotor = $koneksi->query("SELECT 
        COUNT(*) total, 
        SUM(CASE WHEN status='kosong' THEN 1 ELSE 0 END) kosong 
        FROM slot_parkir WHERE jenis_kendaraan='motor'")->fetch_assoc();
    $statSlotMotorTotal = (int)($rMotor['total'] ?? 0);
    $statSlotMotorKosong = (int)($rMotor['kosong'] ?? 0);

    $rMobil = $koneksi->query("SELECT 
        COUNT(*) total, 
        SUM(CASE WHEN status='kosong' THEN 1 ELSE 0 END) kosong 
        FROM slot_parkir WHERE jenis_kendaraan='mobil'")->fetch_assoc();
    $statSlotMobilTotal = (int)($rMobil['total'] ?? 0);
    $statSlotMobilKosong = (int)($rMobil['kosong'] ?? 0);

    // Ambil data tarif untuk kalkulator
    $qTarif = $koneksi->query("SELECT * FROM tarif_parkir");
    while ($tr = $qTarif->fetch_assoc()) {
        $tarifList[$tr['jenis_kendaraan']] = [
            'per_jam' => (float)$tr['tarif_per_jam'],
            'minimal' => (float)$tr['tarif_minimal'],
            'maksimal' => (float)$tr['tarif_maksimal'],
            'denda'    => (float)$tr['denda_hilang_tiket'],
        ];
    }
} catch (Exception $e) {}

// Fallback tarif jika belum ada
if (!isset($tarifList['motor'])) {
    $tarifList['motor'] = ['per_jam' => 2000, 'minimal' => 2000, 'maksimal' => 20000, 'denda' => 25000];
}
if (!isset($tarifList['mobil'])) {
    $tarifList['mobil'] = ['per_jam' => 5000, 'minimal' => 5000, 'maksimal' => 50000, 'denda' => 50000];
}

$persenTersedia = $statSlotTotal > 0 ? round(($statSlotKosong / $statSlotTotal) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($perusahaan['nama_perusahaan']) ?> — Intelligent Urban Parking Platform</title>
<meta name="description" content="Next-generation smart parking and urban mobility management platform for Bogor. Real-time telemetry, automated LPR gates, cashless QRIS, and multi-zone telemetry.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
<style>
/* ============================================================
   MODERN INTERNATIONAL LANDING PAGE STYLES
   ============================================================ */
body {
  background: #090d16;
  color: #f1f5f9;
  overflow-x: hidden;
}

/* NAVBAR */
.nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  z-index: 1000;
  padding: 18px 48px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(9, 13, 22, 0.75);
  backdrop-filter: saturate(180%) blur(20px);
  -webkit-backdrop-filter: saturate(180%) blur(20px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.nav.scrolled {
  padding: 12px 48px;
  background: rgba(9, 13, 22, 0.94);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  border-bottom: 1px solid rgba(37, 99, 235, 0.2);
}
.nav-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  text-decoration: none;
  min-width: 0;
}
.nav-brand-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 22px;
  font-weight: 900;
  box-shadow: 0 4px 18px rgba(37, 99, 235, 0.45);
  border: 1px solid rgba(255, 255, 255, 0.2);
  flex-shrink: 0;
}
.nav-brand-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.nav-brand-text b {
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.3px;
  color: #ffffff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-brand-text small {
  font-size: 10px;
  color: #38bdf8;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.nav-links {
  display: flex;
  gap: 8px;
  align-items: center;
}
.nav-link {
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 13.5px;
  font-weight: 600;
  color: #94a3b8;
  text-decoration: none;
  transition: all 0.2s;
}
.nav-link:hover {
  color: #ffffff;
  background: rgba(255, 255, 255, 0.06);
}

.nav-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

/* HAMBURGER TOGGLE BUTTON */
.nav-hamburger {
  display: none;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  width: 42px;
  height: 42px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  cursor: pointer;
  padding: 0;
  transition: all 0.25s ease;
  flex-shrink: 0;
}
.nav-hamburger:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: rgba(56, 189, 248, 0.4);
}
.nav-bar {
  width: 20px;
  height: 2px;
  background: #ffffff;
  border-radius: 2px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  transform-origin: center;
}
.nav-hamburger.active .nav-bar:nth-child(1) {
  transform: translateY(7px) rotate(45deg);
}
.nav-hamburger.active .nav-bar:nth-child(2) {
  opacity: 0;
  transform: scaleX(0);
}
.nav-hamburger.active .nav-bar:nth-child(3) {
  transform: translateY(-7px) rotate(-45deg);
}

/* MOBILE NAVIGATION DRAWER */
.nav-mobile-drawer {
  position: fixed;
  top: 70px;
  left: 0;
  right: 0;
  background: rgba(9, 13, 22, 0.97);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-bottom: 1px solid rgba(56, 189, 248, 0.25);
  box-shadow: 0 25px 50px rgba(0, 0, 0, 0.8);
  padding: 20px 24px 28px;
  z-index: 999;
  display: none;
  flex-direction: column;
  gap: 18px;
  animation: slideDrawerDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  max-height: calc(100vh - 70px);
  overflow-y: auto;
}
.nav-mobile-drawer.open {
  display: flex;
}
@keyframes slideDrawerDown {
  from { opacity: 0; transform: translateY(-12px); }
  to { opacity: 1; transform: translateY(0); }
}
.nav-mobile-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.nav-mobile-links {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.nav-mobile-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14.5px;
  font-weight: 700;
  color: #cbd5e1;
  text-decoration: none;
  transition: all 0.2s;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid transparent;
}
.nav-mobile-link:hover, .nav-mobile-link:active {
  background: rgba(37, 99, 235, 0.15);
  color: #ffffff;
  border-color: rgba(37, 99, 235, 0.3);
}
.nav-mobile-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.nav-mobile-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 998;
  display: none;
  opacity: 0;
  transition: opacity 0.3s ease;
}
.nav-mobile-backdrop.open {
  display: block;
  opacity: 1;
}

.telemetry-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 700;
  color: #34d399;
}
.radar-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.3);
  animation: pulseRadar 2s infinite;
}
@keyframes pulseRadar {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.4); opacity: 0.6; }
}

/* HERO SECTION */
.hero {
  position: relative;
  padding: 160px 48px 90px;
  max-width: 1400px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  gap: 60px;
  align-items: center;
}
.hero::before {
  content: '';
  position: absolute;
  top: 80px;
  right: -100px;
  width: 650px;
  height: 650px;
  background: radial-gradient(circle, rgba(37, 99, 235, 0.2) 0%, transparent 70%);
  border-radius: 50%;
  z-index: -1;
  pointer-events: none;
}
.hero::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: -150px;
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(6, 182, 212, 0.15) 0%, transparent 70%);
  border-radius: 50%;
  z-index: -1;
  pointer-events: none;
}

.hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 8px 18px;
  background: rgba(37, 99, 235, 0.12);
  color: #60a5fa;
  border-radius: 9999px;
  font-size: 12.5px;
  font-weight: 700;
  margin-bottom: 24px;
  border: 1px solid rgba(59, 130, 246, 0.3);
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.hero h1 {
  font-size: 56px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -2px;
  margin-bottom: 24px;
  color: #ffffff;
}
.hero h1 .grad-text {
  background: linear-gradient(135deg, #60a5fa 0%, #38bdf8 50%, #818cf8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-sub {
  font-size: 17px;
  color: #94a3b8;
  margin-bottom: 36px;
  max-width: 620px;
  line-height: 1.7;
}

.hero-cta {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 48px;
}

.hero-stats-ribbon {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
  padding-top: 32px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}
.hero-stat-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.hero-stat-item .num {
  font-size: 34px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -1px;
  font-family: 'JetBrains Mono', monospace;
}
.hero-stat-item .num.green { color: #34d399; }
.hero-stat-item .num.cyan { color: #38bdf8; }
.hero-stat-item .lbl {
  font-size: 12px;
  color: #94a3b8;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
}

/* TERMINAL INTERACTIVE MOCKUP */
.terminal-window {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 20px;
  box-shadow: 0 25px 60px -10px rgba(0, 0, 0, 0.7), 0 0 40px rgba(37, 99, 235, 0.2);
  overflow: hidden;
  position: relative;
}
.terminal-header {
  background: #1f2937;
  padding: 14px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.terminal-dots {
  display: flex;
  gap: 8px;
}
.terminal-dot {
  width: 12px; height: 12px;
  border-radius: 50%;
}
.terminal-dot.red { background: #ef4444; }
.terminal-dot.yellow { background: #f59e0b; }
.terminal-dot.green { background: #10b981; }

.terminal-title {
  font-size: 12px;
  font-family: 'JetBrains Mono', monospace;
  color: #94a3b8;
  font-weight: 600;
}

.terminal-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.anpr-scan-card {
  background: #0b0f19;
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
  0% { transform: translateY(0); }
  50% { transform: translateY(80px); }
  100% { transform: translateY(0); }
}

.anpr-status {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  font-size: 12px;
  font-weight: 700;
}
.plate-display {
  background: #020617;
  border: 2px solid #334155;
  border-radius: 8px;
  padding: 12px 20px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: 4px;
  color: #ffffff;
  text-align: center;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.8);
}

.gate-info-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
.gate-info-box {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 10px;
  padding: 12px 14px;
}
.gate-info-box small {
  font-size: 10.5px;
  color: #64748b;
  text-transform: uppercase;
  font-weight: 700;
  display: block;
  margin-bottom: 4px;
}
.gate-info-box b {
  font-size: 14px;
  color: #e2e8f0;
  font-family: 'JetBrains Mono', monospace;
}

/* SECTION CONTAINER */
.section {
  padding: 100px 48px;
  max-width: 1400px;
  margin: 0 auto;
}
.section-head {
  text-align: center;
  max-width: 780px;
  margin: 0 auto 60px;
}
.section-tag {
  display: inline-block;
  padding: 6px 16px;
  background: rgba(37, 99, 235, 0.12);
  color: #60a5fa;
  border-radius: 9999px;
  font-size: 11.5px;
  font-weight: 800;
  letter-spacing: 1px;
  text-transform: uppercase;
  margin-bottom: 16px;
  border: 1px solid rgba(37, 99, 235, 0.25);
}
.section-head h2 {
  font-size: 42px;
  font-weight: 800;
  letter-spacing: -1.5px;
  margin-bottom: 18px;
  color: #ffffff;
  line-height: 1.15;
}
.section-head p {
  color: #94a3b8;
  font-size: 16.5px;
  line-height: 1.7;
}

/* TELEMETRY WIDGET */
.telemetry-card {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  padding: 36px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
  margin-bottom: 40px;
}
.telemetry-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
}
.telemetry-item {
  background: #090d16;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.telemetry-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.telemetry-progress-bg {
  height: 8px;
  background: #1e293b;
  border-radius: 9999px;
  overflow: hidden;
}
.telemetry-progress-fill {
  height: 100%;
  border-radius: 9999px;
  transition: width 1s ease;
}

/* BENTO GRID (ARSITEKTUR & FITUR) */
.bento-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 28px;
}
.bento-item {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  padding: 34px 28px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
}
.bento-item:hover {
  transform: translateY(-6px);
  border-color: rgba(37, 99, 235, 0.45);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 0 0 30px rgba(37, 99, 235, 0.2);
}
.bento-item-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
}
.bento-pill {
  font-size: 10.5px;
  font-weight: 800;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  padding: 4px 12px;
  border-radius: 9999px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #94a3b8;
}
.bento-pill.blue { background: rgba(37, 99, 235, 0.15); border-color: rgba(37, 99, 235, 0.3); color: #60a5fa; }
.bento-pill.cyan { background: rgba(6, 182, 212, 0.15); border-color: rgba(6, 182, 212, 0.3); color: #38bdf8; }
.bento-pill.emerald { background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); color: #34d399; }
.bento-pill.amber { background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.3); color: #fbbf24; }
.bento-pill.indigo { background: rgba(99, 102, 241, 0.15); border-color: rgba(99, 102, 241, 0.3); color: #a5b4fc; }
.bento-pill.purple { background: rgba(168, 85, 247, 0.15); border-color: rgba(168, 85, 247, 0.3); color: #c084fc; }

.bento-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: rgba(37, 99, 235, 0.12);
  color: #60a5fa;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(37, 99, 235, 0.25);
  box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
}
.bento-icon svg { width: 24px; height: 24px; }
.bento-icon.cyan { background: rgba(6, 182, 212, 0.12); color: #38bdf8; border-color: rgba(6, 182, 212, 0.25); box-shadow: 0 4px 16px rgba(6, 182, 212, 0.2); }
.bento-icon.emerald { background: rgba(16, 185, 129, 0.12); color: #34d399; border-color: rgba(16, 185, 129, 0.25); box-shadow: 0 4px 16px rgba(16, 185, 129, 0.2); }
.bento-icon.amber { background: rgba(245, 158, 11, 0.12); color: #fbbf24; border-color: rgba(245, 158, 11, 0.25); box-shadow: 0 4px 16px rgba(245, 158, 11, 0.2); }
.bento-icon.indigo { background: rgba(99, 102, 241, 0.12); color: #a5b4fc; border-color: rgba(99, 102, 241, 0.25); box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2); }
.bento-icon.purple { background: rgba(168, 85, 247, 0.12); color: #c084fc; border-color: rgba(168, 85, 247, 0.25); box-shadow: 0 4px 16px rgba(168, 85, 247, 0.2); }

.bento-body {
  flex: 1;
}
.bento-item h3 {
  font-size: 19px;
  font-weight: 800;
  margin-bottom: 12px;
  color: #ffffff;
  letter-spacing: -0.4px;
  line-height: 1.35;
}
.bento-item p {
  color: #94a3b8;
  font-size: 14px;
  line-height: 1.68;
}
.bento-footer {
  margin-top: 24px;
  padding-top: 18px;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: #64748b;
  font-weight: 600;
}
.bento-footer .bento-highlight {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #cbd5e1;
}

/* TARIFF CALCULATOR */
.calc-card {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  padding: 40px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 40px;
  align-items: center;
}
.calc-types {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 24px;
}
.calc-type-btn {
  padding: 16px;
  border-radius: 12px;
  background: #0b0f19;
  border: 2px solid rgba(255, 255, 255, 0.1);
  color: #94a3b8;
  font-weight: 700;
  font-size: 14px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.2s;
}
.calc-type-btn.active {
  background: rgba(37, 99, 235, 0.15);
  border-color: #2563eb;
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
}

.calc-slider-wrap {
  margin-bottom: 24px;
}
.calc-slider {
  width: 100%;
  height: 8px;
  border-radius: 4px;
  background: #1e293b;
  outline: none;
  -webkit-appearance: none;
}
.calc-slider::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #2563eb;
  cursor: pointer;
  border: 3px solid #ffffff;
  box-shadow: 0 0 10px rgba(37, 99, 235, 0.5);
}

.calc-result-box {
  background: #090d16;
  border: 1px solid rgba(37, 99, 235, 0.3);
  border-radius: 18px;
  padding: 32px;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.calc-price {
  font-size: 42px;
  font-weight: 800;
  color: #38bdf8;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: -1px;
}

/* COMPLIANCE & TRUST */
.trust-strip {
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding: 40px 48px;
  background: rgba(255, 255, 255, 0.015);
}
.trust-inner {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  justify-content: space-around;
  align-items: center;
  flex-wrap: wrap;
  gap: 24px;
}
.trust-item {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  font-weight: 700;
  color: #cbd5e1;
}

/* FAQ ACCORDION */
.faq-grid {
  max-width: 860px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.faq-item {
  background: #111827;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  overflow: hidden;
  transition: all 0.25s;
}
.faq-q {
  width: 100%;
  padding: 22px 26px;
  background: transparent;
  border: none;
  color: #ffffff;
  font-size: 16px;
  font-weight: 700;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  text-align: left;
}
.faq-a {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  padding: 0 26px;
  color: #94a3b8;
  font-size: 14.5px;
  line-height: 1.7;
}
.faq-item.open {
  border-color: rgba(37, 99, 235, 0.4);
}
.faq-item.open .faq-a {
  max-height: 200px;
  padding-bottom: 22px;
}
.faq-toggle {
  font-size: 20px;
  color: #38bdf8;
  transition: transform 0.3s;
}
.faq-item.open .faq-toggle {
  transform: rotate(45deg);
}

/* FOOTER */
.landing-footer {
  background: #050811;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  padding: 80px 48px 36px;
}
.landing-footer-grid {
  max-width: 1400px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 48px;
  margin-bottom: 60px;
}
.landing-footer h4 {
  font-size: 14px;
  font-weight: 800;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 20px;
}
.landing-footer ul {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.landing-footer ul a {
  color: #94a3b8;
  font-size: 14px;
  transition: color 0.2s;
}
.landing-footer ul a:hover {
  color: #38bdf8;
}

/* ============================================================
   ENTERPRISE RESPONSIVE SYSTEM (MOBILE SIMULATOR & ALL VIEWPORTS)
   Standard: iPhone SE (320px-375px), iPhone 12/13/14/15/16 (390px-430px),
   Android Galaxy/Pixel (360px-412px), Tablet (768px-1024px), Desktop (1200px+)
   ============================================================ */
@media (max-width: 1024px) {
  .nav { padding: 14px 32px; }
  .nav-links { display: none; }
  .nav-hamburger { display: flex; }
  .hero {
    grid-template-columns: 1fr;
    padding: 130px 32px 70px;
    gap: 48px;
  }
  .bento-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; }
  .calc-card { grid-template-columns: 1fr; gap: 32px; padding: 32px; }
  .landing-footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
}

@media (max-width: 768px) {
  .nav { padding: 12px 20px; }
  .nav-actions .telemetry-pill { display: none; }
  .nav-actions .nav-btn-desktop { display: none; }
  .nav-hamburger { display: flex; }
  .hero { padding: 110px 20px 50px; gap: 36px; }
  .hero h1 { font-size: 34px; letter-spacing: -1.2px; }
  .section { padding: 70px 20px; }
  .bento-grid { grid-template-columns: 1fr; gap: 18px; }
  .trust-strip { padding: 30px 20px; }
  .trust-inner { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
}

@media (max-width: 640px) {
  html, body {
    overflow-x: hidden;
    width: 100%;
    max-width: 100vw;
  }
  .nav {
    padding: 12px 16px;
    height: 64px;
  }
  .nav-mobile-drawer {
    top: 64px;
    max-height: calc(100vh - 64px);
    padding: 16px 18px 24px;
  }
  .nav-brand { gap: 10px; }
  .nav-brand-icon {
    width: 36px;
    height: 36px;
    font-size: 18px;
    border-radius: 10px;
  }
  .nav-brand-text b { font-size: 14px; max-width: 190px; }
  .nav-brand-text small { font-size: 9px; max-width: 190px; }
  
  .hero {
    padding: 85px 16px 40px;
    gap: 28px;
  }
  .hero-badge {
    font-size: 11px;
    padding: 6px 12px;
    margin-bottom: 16px;
  }
  .hero h1 {
    font-size: 28px;
    line-height: 1.22;
    letter-spacing: -0.6px;
    margin-bottom: 16px;
    word-break: break-word;
  }
  .hero-sub {
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 22px;
  }
  
  /* Full-width touch CTA */
  .hero-cta {
    flex-direction: column;
    gap: 10px;
    margin-bottom: 28px;
  }
  .hero-cta .btn {
    width: 100%;
    justify-content: center;
    text-align: center;
    padding: 13px 18px;
    font-size: 14px;
  }

  /* Sleek responsive stats cards on mobile */
  .hero-stats-ribbon {
    grid-template-columns: 1fr;
    gap: 10px;
    padding-top: 20px;
  }
  .hero-stat-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 12px 16px;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
  .hero-stat-item .num {
    font-size: 24px;
    order: 2;
  }
  .hero-stat-item .lbl {
    font-size: 12px;
    order: 1;
  }

  /* Terminal window simulator */
  .terminal-window {
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
  }
  .terminal-header { padding: 12px 14px; }
  .terminal-body { padding: 14px; gap: 12px; }
  .anpr-scan-card { padding: 12px; border-radius: 12px; }
  .anpr-status { font-size: 11px; margin-bottom: 8px; }
  .plate-display {
    font-size: 20px;
    letter-spacing: 2.5px;
    padding: 10px 12px;
    border-radius: 8px;
  }
  .gate-info-row {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  .gate-info-box {
    padding: 8px 10px;
    border-radius: 8px;
  }
  .gate-info-box small { font-size: 9px; margin-bottom: 2px; }
  .gate-info-box b { font-size: 11.5px; }

  /* Sections */
  .section { padding: 50px 16px; }
  .section-head { margin-bottom: 30px; }
  .section-tag { font-size: 10.5px; padding: 5px 12px; margin-bottom: 12px; }
  .section-head h2 { font-size: 25px; letter-spacing: -0.6px; margin-bottom: 12px; line-height: 1.25; }
  .section-head p { font-size: 14px; line-height: 1.6; }

  /* Telemetry card */
  .telemetry-card {
    padding: 18px 14px;
    border-radius: 16px;
    margin-bottom: 24px;
  }
  .telemetry-grid {
    grid-template-columns: 1fr;
    gap: 14px;
  }
  .telemetry-item {
    padding: 16px 14px;
    border-radius: 12px;
    gap: 12px;
  }

  /* Bento Grid */
  .bento-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .bento-item {
    padding: 20px 16px;
    border-radius: 16px;
  }
  .bento-item-top { margin-bottom: 14px; }
  .bento-icon { width: 44px; height: 44px; border-radius: 12px; }
  .bento-icon svg { width: 22px; height: 22px; }
  .bento-pill { font-size: 9.5px; padding: 3px 10px; }
  .bento-item h3 { font-size: 16.5px; margin-bottom: 8px; }
  .bento-item p { font-size: 13px; line-height: 1.6; }
  .bento-footer { margin-top: 16px; padding-top: 12px; font-size: 11px; }

  /* Tariff Calculator */
  .calc-card {
    padding: 20px 16px;
    border-radius: 16px;
    gap: 20px;
  }
  .calc-types { gap: 8px; margin-bottom: 16px; }
  .calc-type-btn { padding: 12px 8px; font-size: 12.5px; border-radius: 10px; }
  .calc-result-box { padding: 20px 14px; border-radius: 14px; gap: 10px; }
  .calc-price { font-size: 28px; }

  /* FAQ Accordion */
  .faq-q { padding: 15px 16px; font-size: 13.5px; gap: 8px; }
  .faq-a { padding: 0 16px; font-size: 13px; }
  .faq-item.open .faq-a { padding-bottom: 16px; }

  /* Footer */
  .landing-footer { padding: 44px 16px 24px; }
  .landing-footer-grid { grid-template-columns: 1fr; gap: 28px; margin-bottom: 30px; }
}

@media (max-width: 380px) {
  .nav-brand-text b { max-width: 140px; }
  .nav-brand-text small { max-width: 140px; }
  .hero h1 { font-size: 24px; }
  .plate-display { font-size: 17px; letter-spacing: 1.5px; }
  .gate-info-row { grid-template-columns: 1fr; }
  .trust-inner { grid-template-columns: 1fr; }
  .calc-price { font-size: 24px; }
}
</style>
</head>
<body>

<!-- ============================================================
     GLOBAL FROSTED NAVBAR WITH MOBILE HAMBURGER
     ============================================================ -->
<nav class="nav" id="nav">
  <a href="index.php" class="nav-brand">
    <div class="nav-brand-icon">🅿️</div>
    <div class="nav-brand-text">
      <b><?= e($perusahaan['nama_perusahaan']) ?></b>
      <small>Smart Urban Mobility Platform</small>
    </div>
  </a>

  <div class="nav-links">
    <a href="#telemetry" class="nav-link">Live Telemetri</a>
    <a href="#fitur" class="nav-link">Fitur Platform</a>
    <a href="#kalkulator" class="nav-link">Kalkulator Tarif</a>
    <a href="#alur" class="nav-link">Alur Layanan</a>
    <a href="#faq" class="nav-link">FAQ</a>
  </div>

  <div class="nav-actions">
    <div class="telemetry-pill">
      <span class="radar-pulse"></span>
      <span>99.98% Telemetry Active</span>
    </div>

    <a href="kiosk.php" class="btn btn-outline btn-sm nav-btn-desktop" target="_blank" style="border-color:rgba(56,189,248,0.4);color:#38bdf8;">
      🖥️ Kiosk
    </a>

    <?php if ($sudahLogin): ?>
      <a href="<?= $dash_url ?>" class="btn btn-primary btn-sm nav-btn-desktop">
        📊 <?= e($dash_label) ?>
      </a>
    <?php else: ?>
      <a href="login.php" class="btn btn-primary btn-sm nav-btn-desktop">
        🔐 Login Petugas
      </a>
    <?php endif; ?>

    <!-- Mobile Hamburger Toggle Button -->
    <button class="nav-hamburger" id="navToggle" type="button" aria-label="Buka Menu Navigasi" aria-expanded="false">
      <span class="nav-bar"></span>
      <span class="nav-bar"></span>
      <span class="nav-bar"></span>
    </button>
  </div>
</nav>

<!-- Mobile Navigation Drawer / Slide-down Menu -->
<div class="nav-mobile-drawer" id="mobileDrawer">
  <div class="nav-mobile-header">
    <div class="telemetry-pill" style="display:inline-flex;">
      <span class="radar-pulse"></span>
      <span>99.98% Live IoT Active</span>
    </div>
  </div>
  <div class="nav-mobile-links">
    <a href="#telemetry" class="nav-mobile-link">
      <span>📡</span> <span>Live Telemetri Bay</span>
    </a>
    <a href="#fitur" class="nav-mobile-link">
      <span>⚡</span> <span>Fitur & Arsitektur</span>
    </a>
    <a href="#kalkulator" class="nav-mobile-link">
      <span>🧮</span> <span>Kalkulator Tarif</span>
    </a>
    <a href="#alur" class="nav-mobile-link">
      <span>🔄</span> <span>Alur Layanan Parkir</span>
    </a>
    <a href="#faq" class="nav-mobile-link">
      <span>❓</span> <span>Pusat Bantuan & FAQ</span>
    </a>
  </div>
  <div class="nav-mobile-actions">
    <a href="kiosk.php" class="btn btn-outline btn-block" target="_blank" style="border-color:rgba(56,189,248,0.4);color:#38bdf8;padding:12px;text-align:center;">
      🖥️ Terminal Kiosk Mandiri
    </a>
    <?php if ($sudahLogin): ?>
      <a href="<?= $dash_url ?>" class="btn btn-primary btn-block" style="padding:12px;text-align:center;">
        📊 <?= e($dash_label) ?>
      </a>
    <?php else: ?>
      <a href="login.php" class="btn btn-primary btn-block" style="padding:12px;text-align:center;">
        🔐 Login Petugas / Admin
      </a>
    <?php endif; ?>
  </div>
</div>
<div class="nav-mobile-backdrop" id="mobileBackdrop"></div>

<!-- ============================================================
     HERO SECTION WITH ANPR TERMINAL PREVIEW
     ============================================================ -->
<header class="hero">
  <div>
    <span class="hero-badge">
      <span class="radar-pulse" style="width:6px;height:6px;"></span>
      Next-Gen Smart Parking Architecture
    </span>
    <h1>
      Intelligent Urban Mobility & <br>
      <span class="grad-text">Frictionless Parking</span>
    </h1>
    <p class="hero-sub">
      Platform manajemen parkir cerdas kelas dunia untuk <?= e($perusahaan['nama_perusahaan']) ?> di Kota Bogor.
      Mengintegrasikan optical plate recognition, telemetri bay real-time, gate otomatis, dan transaksi cashless multi-metode.
    </p>

    <div class="hero-cta">
      <?php if ($sudahLogin): ?>
        <a href="<?= $dash_url ?>" class="btn btn-primary btn-lg" style="box-shadow:0 10px 30px rgba(37,99,235,0.4);">
          🚀 Buka Dashboard Saya
        </a>
        <a href="kiosk.php" class="btn btn-outline btn-lg" target="_blank" style="border-color:rgba(255,255,255,0.2);color:#fff;">
          🎫 Kiosk Mandiri
        </a>
      <?php else: ?>
        <a href="register.php" class="btn btn-primary btn-lg" style="box-shadow:0 10px 30px rgba(37,99,235,0.4);">
          📝 Registrasi Pelanggan
        </a>
        <a href="login.php" class="btn btn-secondary btn-lg">
          🔐 Login Petugas / Admin
        </a>
      <?php endif; ?>
    </div>

    <div class="hero-stats-ribbon">
      <div class="hero-stat-item">
        <span class="num counter" data-target="<?= $statSlotTotal ?>"><?= $statSlotTotal ?></span>
        <span class="lbl">Total Kapasitas Bay</span>
      </div>
      <div class="hero-stat-item">
        <span class="num green counter" data-target="<?= $statSlotKosong ?>"><?= $statSlotKosong ?></span>
        <span class="lbl">Bay Tersedia Saat Ini</span>
      </div>
      <div class="hero-stat-item">
        <span class="num cyan counter" data-target="<?= $statKendaraan ?>"><?= $statKendaraan ?></span>
        <span class="lbl">Kendaraan Terlayani</span>
      </div>
    </div>
  </div>

  <!-- INTERACTIVE TERMINAL SIMULATION -->
  <div>
    <div class="terminal-window">
      <div class="terminal-header">
        <div class="terminal-dots">
          <div class="terminal-dot red"></div>
          <div class="terminal-dot yellow"></div>
          <div class="terminal-dot green"></div>
        </div>
        <div class="terminal-title">GATE_01 // ANPR_BARRIER_FEED</div>
        <span class="badge badge-success" style="font-size:10px;padding:2px 8px;">LIVE SCANNER</span>
      </div>
      <div class="terminal-body">
        <div class="anpr-scan-card">
          <div class="anpr-status">
            <span style="color:#38bdf8;">● OPTICAL PLATE RECOGNITION</span>
            <span style="color:#34d399;font-family:'JetBrains Mono';">CONFIDENCE: 99.4%</span>
          </div>
          <div class="plate-display" id="anprPlate">F 1428 KLR</div>
        </div>

        <div class="gate-info-row">
          <div class="gate-info-box">
            <small>Status Barrier Gate</small>
            <b style="color:#34d399;">GATE OPENED [AUTO]</b>
          </div>
          <div class="gate-info-box">
            <small>Tiket Digital QR</small>
            <b style="color:#38bdf8;">TKT-<?= date('ymd') ?>-882</b>
          </div>
          <div class="gate-info-box">
            <small>Alokasi Slot Parkir</small>
            <b style="color:#f59e0b;">ZONA A · BAY C-04</b>
          </div>
          <div class="gate-info-box">
            <small>Metode Pembayaran</small>
            <b style="color:#e2e8f0;">QRIS / CASHLESS READY</b>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- ============================================================
     TRUST ARCHITECTURE STRIP
     ============================================================ -->
<div class="trust-strip">
  <div class="trust-inner">
    <div class="trust-item">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>ISO 27001 Certified Security</span>
    </div>
    <div class="trust-item">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
      <span>99.98% High-Availability Uptime</span>
    </div>
    <div class="trust-item">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      <span>QRIS & Bank Indonesia Gateway</span>
    </div>
    <div class="trust-item">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
      <span>Sub-3-Second Gate Latency</span>
    </div>
  </div>
</div>

<!-- ============================================================
     LIVE TELEMETRY SECTION
     ============================================================ -->
<section class="section" id="telemetry">
  <div class="section-head">
    <span class="section-tag">Real-Time Telemetry</span>
    <h2>Monitoring Okupansi Bay Parkir</h2>
    <p>Data kapasitas real-time yang tersinkronisasi otomatis dengan sensor slot parkir dan sistem barrier gate.</p>
  </div>

  <div class="telemetry-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
      <div>
        <h3 style="font-size:20px;font-weight:800;color:#fff;">Kapasitas Keseluruhan Area</h3>
        <p style="color:#94a3b8;font-size:13.5px;">Lokasi: <?= e($perusahaan['alamat'] ?? 'Kota Bogor') ?></p>
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <span class="badge badge-success" style="padding:6px 14px;font-size:12px;">
          <?= $persenTersedia ?>% Slot Kosong
        </span>
      </div>
    </div>

    <div class="telemetry-grid">
      <!-- Motor Slot Box -->
      <div class="telemetry-item">
        <div class="telemetry-header">
          <div>
            <span style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;">🏍️ Slot Motor (Zona A/B)</span>
            <div style="font-size:28px;font-weight:800;color:#fff;font-family:'JetBrains Mono';margin-top:4px;">
              <?= $statSlotMotorKosong ?> <span style="font-size:15px;color:#64748b;">/ <?= $statSlotMotorTotal ?></span>
            </div>
          </div>
          <span class="badge badge-primary">Tersedia</span>
        </div>
        <div class="telemetry-progress-bg">
          <?php $pctMotor = $statSlotMotorTotal > 0 ? round(($statSlotMotorKosong / $statSlotMotorTotal) * 100) : 0; ?>
          <div class="telemetry-progress-fill" style="width: <?= $pctMotor ?>%; background: #3b82f6;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;">
          <span>Okupansi: <?= 100 - $pctMotor ?>%</span>
          <span style="color:#38bdf8;">Tarif: Rp <?= number_format($tarifList['motor']['per_jam'], 0, ',', '.') ?>/jam</span>
        </div>
      </div>

      <!-- Mobil Slot Box -->
      <div class="telemetry-item">
        <div class="telemetry-header">
          <div>
            <span style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;">🚗 Slot Mobil (Zona C)</span>
            <div style="font-size:28px;font-weight:800;color:#fff;font-family:'JetBrains Mono';margin-top:4px;">
              <?= $statSlotMobilKosong ?> <span style="font-size:15px;color:#64748b;">/ <?= $statSlotMobilTotal ?></span>
            </div>
          </div>
          <span class="badge badge-success">Tersedia</span>
        </div>
        <div class="telemetry-progress-bg">
          <?php $pctMobil = $statSlotMobilTotal > 0 ? round(($statSlotMobilKosong / $statSlotMobilTotal) * 100) : 0; ?>
          <div class="telemetry-progress-fill" style="width: <?= $pctMobil ?>%; background: #10b981;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:#94a3b8;">
          <span>Okupansi: <?= 100 - $pctMobil ?>%</span>
          <span style="color:#34d399;">Tarif: Rp <?= number_format($tarifList['mobil']['per_jam'], 0, ',', '.') ?>/jam</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     BENTO GRID FEATURE SHOWCASE
     ============================================================ -->
<section class="section" id="fitur">
  <div class="section-head">
    <span class="section-tag">Arsitektur & Fitur</span>
    <h2>Didesain untuk Standar Smart City Global</h2>
    <p>Setiap modul dibangun dengan presisi tinggi guna menjamin kecepatan transaksi, akurasi audit, dan kemudahan pengguna.</p>
  </div>

  <div class="bento-grid">
    <!-- Card 1: ANPR Ready -->
    <div class="bento-item">
      <div>
        <div class="bento-item-top">
          <div class="bento-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          </div>
          <span class="bento-pill blue">AI Vision</span>
        </div>
        <div class="bento-body">
          <h3>Automated License Plate Recognition (ANPR)</h3>
          <p>
            Integrasi kamera optik AI cerdas untuk mendeteksi nomor plat kendaraan secara instan saat mendekati palang pintu. Memangkas antrean dengan latensi di bawah 3 detik.
          </p>
        </div>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">⚡ Sub-3s Detection</span>
        <span>Akurasi 99.4%</span>
      </div>
    </div>

    <!-- Card 2: Autonomous Kiosk -->
    <div class="bento-item">
      <div>
        <div class="bento-item-top">
          <div class="bento-icon cyan">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          </div>
          <span class="bento-pill cyan">Self-Service</span>
        </div>
        <div class="bento-body">
          <h3>Autonomous Touchscreen Kiosk 24/7</h3>
          <p>
            Mesin terminal tiket mandiri layar sentuh bagi pengunjung. Menerbitkan karcis thermal fisik ber-barcode dan tiket digital QR instan tanpa perlu antre di loket petugas.
          </p>
        </div>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">🖨️ Thermal + QR Pass</span>
        <span>24 Jam Siaga</span>
      </div>
    </div>

    <!-- Card 3: Real-Time Telemetry -->
    <div class="bento-item">
      <div>
        <div class="bento-item-top">
          <div class="bento-icon emerald">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 10 10"/><path d="M12 6a6 6 0 0 1 6 6"/><circle cx="12" cy="12" r="2"/></svg>
          </div>
          <span class="bento-pill emerald">IoT Telemetry</span>
        </div>
        <div class="bento-body">
          <h3>Real-Time Bay Telemetry & Guidance</h3>
          <p>
            Sinkronisasi data sensor ketersediaan slot parkir motor dan mobil secara live. Mengarahkan pengendara langsung ke zona slot kosong terdekat tanpa membuang waktu.
          </p>
        </div>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">📡 Live Sync 99.98%</span>
        <span>Zona A, B, & C</span>
      </div>
    </div>

    <!-- Card 4: Dynamic Pricing -->
    <div class="bento-item">
      <div>
        <div class="bento-item-top">
          <div class="bento-icon amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><line x1="12" y1="6" x2="12" y2="18"/></svg>
          </div>
          <span class="bento-pill amber">Pricing Engine</span>
        </div>
        <div class="bento-body">
          <h3>Dynamic Pricing & Multi-Tariff Engine</h3>
          <p>
            Kalkulasi biaya progresif otomatis per jam dengan batasan tarif minimal, plafon biaya maksimal harian, serta otomasi denda tiket hilang yang terkonfigurasi fleksibel.
          </p>
        </div>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">⚙️ Skema Progresif</span>
        <span>Plafon Maksimal</span>
      </div>
    </div>

    <!-- Card 5: Cashless & QRIS Gateway -->
    <div class="bento-item">
      <div>
        <div class="bento-item-top">
          <div class="bento-icon indigo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="6" cy="15" r="1"/><circle cx="10" cy="15" r="1"/></svg>
          </div>
          <span class="bento-pill indigo">Fintech Gateway</span>
        </div>
        <div class="bento-body">
          <h3>Cashless QRIS & Multi-Channel Payment</h3>
          <p>
            Mendukung pembayaran non-tunai cepat dengan QRIS Dinamis standar Bank Indonesia (GoPay, OVO, BCA, Dana, ShopeePay) serta pembayaran cash dengan perhitungan kembalian instan.
          </p>
        </div>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">💳 Standar QRIS BI</span>
        <span>Cash & Non-Tunai</span>
      </div>
    </div>

    <!-- Card 6: Multi-Role RBAC & Audit Trails -->
    <div class="bento-item">
      <div>
        <div class="bento-item-top">
          <div class="bento-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <span class="bento-pill purple">Enterprise Security</span>
        </div>
        <div class="bento-body">
          <h3>Multi-Role Access & Forensic Audit Trails</h3>
          <p>
            Segregasi hak akses hierarkis (Super Admin, Petugas Gate Pos, dan Pelanggan) dengan pencatatan digital log aktivitas IP permanen untuk transparansi operasional dan anti-fraud.
          </p>
        </div>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">🛡️ ISO 27001 Ready</span>
        <span>Audit Trail Permanen</span>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     INTERACTIVE TARIFF CALCULATOR
     ============================================================ -->
<section class="section" id="kalkulator">
  <div class="section-head">
    <span class="section-tag">Estimasi Cerdas</span>
    <h2>Kalkulator Tarif Parkir Interaktif</h2>
    <p>Hitung perkiraan biaya parkir kendaraan Anda secara instan dan transparan sebelum memasuki area parkir.</p>
  </div>

  <div class="calc-card">
    <div>
      <label style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:10px;display:block;">1. Pilih Jenis Kendaraan</label>
      <div class="calc-types">
        <button type="button" class="calc-type-btn active" id="btnCalcMobil" onclick="selectCalcType('mobil')">
          <span style="font-size:18px;">🚗</span>
          <span>Mobil Pribadi</span>
        </button>
        <button type="button" class="calc-type-btn" id="btnCalcMotor" onclick="selectCalcType('motor')">
          <span style="font-size:18px;">🏍️</span>
          <span>Sepeda Motor</span>
        </button>
      </div>

      <label style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:10px;display:block;">
        2. Durasi Parkir: <span id="calcDurationLabel" style="color:#38bdf8;font-family:'JetBrains Mono';">2 Jam</span>
      </label>
      <div class="calc-slider-wrap">
        <input type="range" min="1" max="24" value="2" class="calc-slider" id="calcSlider" oninput="updateCalcPrice()">
      </div>
      <div style="display:flex;justify-content:space-between;font-size:12px;color:#64748b;font-family:'JetBrains Mono';">
        <span>1 Jam</span>
        <span>6 Jam</span>
        <span>12 Jam</span>
        <span>24 Jam</span>
      </div>
    </div>

    <div>
      <div class="calc-result-box">
        <span style="font-size:11px;color:#94a3b8;font-weight:800;letter-spacing:1px;text-transform:uppercase;">Estimasi Total Biaya</span>
        <div class="calc-price" id="calcResultPrice">Rp 10.000</div>
        <p style="font-size:12.5px;color:#64748b;margin-top:6px;">
          *Termasuk asuransi parkir, PPN 11%, dan pemantauan CCTV 24 Jam.
        </p>
        <div style="margin-top:16px;">
          <a href="kiosk.php" class="btn btn-primary btn-block" target="_blank">
            🎫 Ambil Tiket Sekarang
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     WORKFLOW SECTION
     ============================================================ -->
<section class="section" id="alur">
  <div class="section-head">
    <span class="section-tag">Alur Sistem</span>
    <h2>Tiga Langkah Parkir Otomatis</h2>
    <p>Pengalaman berkendara yang mulus tanpa kerumitan administrasi manual.</p>
  </div>

  <div class="bento-grid">
    <div class="bento-item">
      <div>
        <div style="font-size:32px;font-weight:900;color:#2563eb;font-family:'JetBrains Mono';margin-bottom:14px;">01.</div>
        <h3>Deteksi Plat & Check-in</h3>
        <p>Sistem memindai plat nomor atau pengunjung mengambil tiket digital di Kiosk mandiri. Slot kosong langsung dialokasikan.</p>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">Gate Inbound</span>
        <span style="color:#60a5fa;">Langkah 01</span>
      </div>
    </div>
    <div class="bento-item">
      <div>
        <div style="font-size:32px;font-weight:900;color:#10b981;font-family:'JetBrains Mono';margin-bottom:14px;">02.</div>
        <h3>Monitoring & Telemetri</h3>
        <p>Durasi parkir berjalan terhitung presisi hingga hitungan menit. Status slot berubah menjadi terisi secara real-time.</p>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">Smart Guidance</span>
        <span style="color:#34d399;">Langkah 02</span>
      </div>
    </div>
    <div class="bento-item">
      <div>
        <div style="font-size:32px;font-weight:900;color:#06b6d4;font-family:'JetBrains Mono';margin-bottom:14px;">03.</div>
        <h3>Cashless Payment & Release</h3>
        <p>Scan barcode tiket di pos keluar atau bayar langsung via QRIS / tunai. Palang otomatis terbuka dan struk resmi tercetak.</p>
      </div>
      <div class="bento-footer">
        <span class="bento-highlight">Gate Outbound</span>
        <span style="color:#38bdf8;">Langkah 03</span>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     FAQ ACCORDION
     ============================================================ -->
<section class="section" id="faq">
  <div class="section-head">
    <span class="section-tag">Bantuan & Informasi</span>
    <h2>Pertanyaan yang Sering Diajukan</h2>
    <p>Temukan jawaban cepat seputar pengoperasian dan tarif parkir.</p>
  </div>

  <div class="faq-grid">
    <div class="faq-item">
      <button class="faq-q" onclick="toggleFaq(this)">
        <span>Bagaimana cara menggunakan Kiosk Mandiri tanpa antre?</span>
        <span class="faq-toggle">+</span>
      </button>
      <div class="faq-a">
        <p>Pengunjung cukup mendekati layar Kiosk pada gerbang masuk, mengetikkan plat nomor kendaraan, memilih jenis kendaraan (motor/mobil), dan menekan tombol cetak tiket. Tiket digital berisi kode QR akan diterbitkan dalam tempo 2 detik.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-q" onclick="toggleFaq(this)">
        <span>Metode pembayaran apa saja yang didukung pada pos keluar?</span>
        <span class="faq-toggle">+</span>
      </button>
      <div class="faq-a">
        <p>Sistem mendukung pembayaran Tunai (Cash) dengan perhitungan kembalian otomatis, serta Cashless menggunakan QRIS (BCA, Mandiri, GoPay, OVO, ShopeePay, DANA) dan kartu membership pelanggan terdaftar.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-q" onclick="toggleFaq(this)">
        <span>Bagaimana jika karcis/tiket parkir fisik saya hilang?</span>
        <span class="faq-toggle">+</span>
      </button>
      <div class="faq-a">
        <p>Petugas operator dapat melakukan pencarian data kendaraan berdasarkan plat nomor pada sistem. Denda kehilangan tiket akan dihitung otomatis sesuai ketentuan yang telah ditetapkan manajemen.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     ENTERPRISE FOOTER
     ============================================================ -->
<footer class="landing-footer">
  <div class="landing-footer-grid">
    <div>
      <div class="nav-brand" style="margin-bottom:18px;">
        <div class="nav-brand-icon">🅿️</div>
        <div class="nav-brand-text">
          <b><?= e($perusahaan['nama_perusahaan']) ?></b>
          <small>Urban Mobility OS</small>
        </div>
      </div>
      <p style="color:#94a3b8;font-size:14px;line-height:1.7;max-width:380px;">
        Solusi platform manajemen perparkiran digital terintegrasi untuk pusat perbelanjaan, gedung perkantoran, dan kawasan urban di Kota Bogor.
      </p>
      <p style="color:#64748b;font-size:12.5px;margin-top:16px;">
        <?= e($perusahaan['alamat'] ?? 'Jl. Pajajaran No. 1, Bogor') ?><br>
        Telepon: <?= e($perusahaan['telepon'] ?? '0251-123456') ?> · Jam: <?= e($perusahaan['jam_operasional'] ?? '24 Jam') ?>
      </p>
    </div>

    <div>
      <h4>Navigasi Cepat</h4>
      <ul>
        <li><a href="#telemetry">Telemetri Slot</a></li>
        <li><a href="#fitur">Fitur Platform</a></li>
        <li><a href="#kalkulator">Kalkulator Tarif</a></li>
        <li><a href="#alur">Alur Kerja</a></li>
        <li><a href="#faq">Pusat Bantuan</a></li>
      </ul>
    </div>

    <div>
      <h4>Layanan & Portal</h4>
      <ul>
        <li><a href="kiosk.php" target="_blank">Terminal Kiosk</a></li>
        <li><a href="login.php">Login Petugas</a></li>
        <li><a href="register.php">Registrasi Member</a></li>
        <li><a href="lupa_password.php">Reset Kata Sandi</a></li>
        <?php if ($sudahLogin): ?>
          <li><a href="<?= $dash_url ?>">Dashboard Saya</a></li>
        <?php endif; ?>
      </ul>
    </div>

    <div>
      <h4>Keamanan & Standar</h4>
      <ul>
        <li><span style="color:#94a3b8;font-size:13.5px;">✓ ISO 27001 Data Security</span></li>
        <li><span style="color:#94a3b8;font-size:13.5px;">✓ QRIS National Gateway</span></li>
        <li><span style="color:#94a3b8;font-size:13.5px;">✓ 24/7 Redundant Telemetry</span></li>
        <li><span style="color:#94a3b8;font-size:13.5px;">✓ Automated ANPR Gates</span></li>
      </ul>
    </div>
  </div>

  <div style="max-width:1400px;margin:0 auto;padding-top:28px;border-top:1px solid rgba(255,255,255,0.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;font-size:12.5px;color:#64748b;">
    <div>
      &copy; <?= date('Y') ?> <b><?= e($perusahaan['nama_perusahaan']) ?></b>. All international rights reserved.
    </div>
    <div style="display:flex;gap:16px;">
      <span style="display:flex;align-items:center;gap:6px;color:#34d399;">
        <span class="radar-pulse" style="width:6px;height:6px;"></span>
        Cloud Systems Operational
      </span>
      <span>v<?= defined('APP_VERSION') ? APP_VERSION : '3.2' ?></span>
    </div>
  </div>
</footer>

<!-- SCRIPT LOGIC -->
<script>
// Data Tarif dari Database PHP
const TARIF_DATA = <?= json_encode($tarifList) ?>;
let selectedVehicle = 'mobil';

// Navbar Scroll Effect
window.addEventListener('scroll', function() {
  const nav = document.getElementById('nav');
  if (window.scrollY > 40) {
    nav.classList.add('scrolled');
  } else {
    nav.classList.remove('scrolled');
  }
});

// Kalkulator Tarif Interaktif
function selectCalcType(type) {
  selectedVehicle = type;
  document.getElementById('btnCalcMobil').classList.toggle('active', type === 'mobil');
  document.getElementById('btnCalcMotor').classList.toggle('active', type === 'motor');
  updateCalcPrice();
}

function updateCalcPrice() {
  const hours = parseInt(document.getElementById('calcSlider').value, 10);
  document.getElementById('calcDurationLabel').textContent = hours + ' Jam';

  const cfg = TARIF_DATA[selectedVehicle] || { per_jam: 5000, minimal: 5000, maksimal: 50000 };
  let total = cfg.per_jam * hours;
  if (cfg.minimal && total < cfg.minimal) total = cfg.minimal;
  if (cfg.maksimal && total > cfg.maksimal) total = cfg.maksimal;

  document.getElementById('calcResultPrice').textContent = 'Rp ' + Number(total).toLocaleString('id-ID');
}
updateCalcPrice();

// Simulated ANPR Live Plate Rotation
const samplePlates = ['F 1428 KLR', 'B 8920 PQR', 'D 4519 ZXA', 'B 3021 VBL', 'F 7721 NXZ'];
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
}, 4000);

// FAQ Accordion Toggle
function toggleFaq(btn) {
  const item = btn.parentElement;
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item').forEach(function(el) {
    el.classList.remove('open');
  });
  if (!isOpen) {
    item.classList.add('open');
  }
}

// Mobile Navigation Drawer Toggle
(function() {
  const navToggle = document.getElementById('navToggle');
  const drawer = document.getElementById('mobileDrawer');
  const backdrop = document.getElementById('mobileBackdrop');

  function openDrawer() {
    if (navToggle) {
      navToggle.classList.add('active');
      navToggle.setAttribute('aria-expanded', 'true');
    }
    if (drawer) drawer.classList.add('open');
    if (backdrop) backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (navToggle) {
      navToggle.classList.remove('active');
      navToggle.setAttribute('aria-expanded', 'false');
    }
    if (drawer) drawer.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (navToggle) {
    navToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      if (drawer && drawer.classList.contains('open')) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });
  }

  if (backdrop) {
    backdrop.addEventListener('click', closeDrawer);
  }

  // Close when clicking mobile drawer links
  document.querySelectorAll('.nav-mobile-link').forEach(function(link) {
    link.addEventListener('click', function() {
      closeDrawer();
    });
  });

  // Close on ESC
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) {
      closeDrawer();
    }
  });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>