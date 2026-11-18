<?php
// includes/header.php
if (!isset($koneksi)) { require_once __DIR__ . '/../config/database.php'; }
if (!function_exists('e')) { require_once __DIR__ . '/functions.php'; }
if (!function_exists('is_login')) { require_once __DIR__ . '/auth.php'; }

if (!isset($skip_auth) || !$skip_auth) { cek_login(); }

if (!isset($perusahaan) || !is_array($perusahaan)) {
    $perusahaan = ['nama_perusahaan' => 'PT. Parkir Bogor', 'alamat' => '-', 'telepon' => '-'];
}

$current = basename($_SERVER['PHP_SELF']);
$page_title = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';

$user_role = user_role();
$user_initial = strtoupper(substr($_SESSION['nama_lengkap'] ?? 'U', 0, 1));

// ==========================================
// KONFIGURASI MENU BERDASARKAN ROLE
// ==========================================
$menu = [];
$role_label = '';

if ($user_role === 'admin') {
    $role_label = 'Admin';
    $menu = [
        ['file'=>'dashboard.php', 'icon'=>'dashboard', 'label'=>'Dashboard', 'path'=>PATH_ADMIN],
        ['file'=>'riwayat.php', 'icon'=>'receipt', 'label'=>'Transaksi Parkir', 'path'=>PATH_ADMIN],
        ['file'=>'slot.php', 'icon'=>'parking', 'label'=>'Slot Parkir', 'path'=>PATH_ADMIN],
        ['file'=>'tarif.php', 'icon'=>'tag', 'label'=>'Tarif Parkir', 'path'=>PATH_ADMIN],
        ['file'=>'operator.php', 'icon'=>'users', 'label'=>'Petugas Operator', 'path'=>PATH_ADMIN],
        ['file'=>'pelanggan.php', 'icon'=>'people', 'label'=>'Data Pelanggan', 'path'=>PATH_ADMIN],
        ['file'=>'laporan.php', 'icon'=>'chart', 'label'=>'Laporan Pendapatan', 'path'=>PATH_ADMIN],
        ['file'=>'log_aktivitas.php', 'icon'=>'log', 'label'=>'Log Aktivitas', 'path'=>PATH_ADMIN],
    ];
} elseif ($user_role === 'operator') {
    $role_label = 'Operator';
    $menu = [
        ['file'=>'dashboard.php', 'icon'=>'dashboard', 'label'=>'Dashboard', 'path'=>PATH_OPERATOR],
        ['file'=>'masuk.php', 'icon'=>'arrow-in', 'label'=>'Parkir Masuk', 'path'=>PATH_OPERATOR],
        ['file'=>'keluar.php', 'icon'=>'arrow-out', 'label'=>'Parkir Keluar', 'path'=>PATH_OPERATOR],
        ['file'=>'kendaraan_aktif.php', 'icon'=>'car', 'label'=>'Kendaraan Aktif', 'path'=>PATH_OPERATOR],
        ['file'=>'riwayat.php', 'icon'=>'history', 'label'=>'Riwayat Transaksi', 'path'=>PATH_OPERATOR],
    ];
} else {
    $role_label = 'Pelanggan';
    $menu = [
        ['file'=>'dashboard.php', 'icon'=>'dashboard', 'label'=>'Dashboard', 'path'=>PATH_PUBLIC],
        ['file'=>'kendaraan_saya.php', 'icon'=>'car', 'label'=>'Kendaraan Saya', 'path'=>PATH_PUBLIC],
        ['file'=>'masuk.php', 'icon'=>'parking', 'label'=>'Parkir Sekarang', 'path'=>PATH_PUBLIC],
        ['file'=>'keluar.php', 'icon'=>'wallet', 'label'=>'Bayar & Keluar', 'path'=>PATH_PUBLIC],
        ['file'=>'riwayat.php', 'icon'=>'history', 'label'=>'Riwayat Transaksi', 'path'=>PATH_PUBLIC],
    ];
}

// SVG icon map
$svg_icons = [
    'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    'receipt' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2l-3 2-3-2-3 2-3-2-3 2Z"/><path d="M8 10h8"/><path d="M8 14h4"/></svg>',
    'parking' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg>',
    'tag' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.42 0l6.58-6.58a1 1 0 0 0 0-1.42Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
    'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'people' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/></svg>',
    'chart' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 16 4-8 4 4 4-6"/></svg>',
    'log' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>',
    'arrow-in' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
    'arrow-out' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    'car' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18 10l-2.7-3.6A1 1 0 0 0 14.5 6h-5a1 1 0 0 0-.8.4L6 10l-2.5 1.1C2.7 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>',
    'history' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'wallet' => '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> — <?= e($perusahaan['nama_perusahaan']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css?v=<?= time() ?>">

<style>
    /* ===== LAYOUT SYSTEM ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif; background: #090d16; color: #f1f5f9; display: flex; min-height: 100vh; overflow-x: hidden; }
    a { text-decoration: none; }

    .app-layout { display: flex; width: 100%; min-height: 100vh; }

    /* ===== SIDEBAR ===== */
    .sidebar {
        width: 270px; background: #0b0f1a; border-right: 1px solid rgba(255,255,255,0.06);
        color: #fff; display: flex; flex-direction: column; flex-shrink: 0;
        position: fixed; top: 0; bottom: 0; left: 0; z-index: 100;
        overflow-y: auto; overflow-x: hidden;
        scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.06) transparent;
    }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.08); border-radius: 4px; }

    .sidebar-brand {
        padding: 24px 22px; border-bottom: 1px solid rgba(255,255,255,0.06);
        display: flex; align-items: center; gap: 14px;
    }
    .sidebar-brand-icon {
        width: 40px; height: 40px; border-radius: 12px;
        background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; color: #fff;
        box-shadow: 0 4px 18px rgba(37,99,235,0.45);
        border: 1px solid rgba(255,255,255,0.2);
        flex-shrink: 0;
    }
    .sidebar-brand-text b {
        display: block; font-size: 15px; font-weight: 800; color: #f8fafc;
        letter-spacing: -0.3px; line-height: 1.3;
    }
    .sidebar-brand-text small {
        display: block; font-size: 10px; color: #38bdf8;
        text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-top: 2px;
    }

    .sidebar-profile {
        padding: 18px 22px; display: flex; align-items: center; gap: 12px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        background: rgba(255,255,255,0.02);
    }
    .sidebar-avatar {
        width: 42px; height: 42px; border-radius: 12px;
        background: rgba(37,99,235,0.15);
        border: 1px solid rgba(37,99,235,0.3);
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 16px; color: #60a5fa;
        flex-shrink: 0; transition: all 0.3s ease;
    }
    .sidebar-profile:hover .sidebar-avatar {
        border-color: #2563eb;
        box-shadow: 0 0 16px rgba(37,99,235,0.3);
    }
    .sidebar-profile-info b { display: block; font-size: 13.5px; color: #f1f5f9; font-weight: 700; }
    .sidebar-profile-info span {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 11px; color: #94a3b8; font-weight: 600;
        margin-top: 2px;
    }
    .sidebar-profile-info .role-dot {
        width: 6px; height: 6px; border-radius: 50%;
        background: #10b981; display: inline-block;
        box-shadow: 0 0 6px rgba(16,185,129,0.5);
    }

    .sidebar-menu { list-style: none; padding: 14px 12px; display: flex; flex-direction: column; gap: 2px; flex: 1; }
    .sidebar-menu-section {
        font-size: 10px; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1.2px; color: #475569;
        margin: 16px 10px 8px; padding-bottom: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .sidebar-menu a {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 14px; border-radius: 12px;
        color: #94a3b8; font-size: 13.5px; font-weight: 600;
        transition: all 0.2s cubic-bezier(0.4,0,0.2,1); position: relative;
        border: 1px solid transparent;
    }
    .sidebar-menu a:hover {
        background: rgba(255,255,255,0.06); color: #ffffff;
        border-color: rgba(255,255,255,0.08);
    }
    .sidebar-menu a.active {
        background: rgba(37,99,235,0.18); color: #ffffff;
        font-weight: 800; border-color: rgba(37,99,235,0.35);
        box-shadow: 0 2px 14px rgba(37,99,235,0.25);
    }
    .sidebar-menu a.active .sidebar-menu-icon { color: #60a5fa; }
    .sidebar-menu-icon {
        width: 20px; height: 20px; display: flex;
        align-items: center; justify-content: center;
        flex-shrink: 0; color: #64748b; transition: color 0.2s;
    }
    .sidebar-menu a:hover .sidebar-menu-icon { color: #ffffff; }

    .sidebar-footer {
        margin-top: auto; padding: 16px 22px;
        font-size: 11px; text-align: center; color: #64748b;
        border-top: 1px solid rgba(255,255,255,0.06);
        background: rgba(9,13,22,0.6);
    }

    /* ===== MAIN CONTENT ===== */
    .main {
        margin-left: 270px; flex: 1; display: flex; flex-direction: column;
        min-width: 0; min-height: 100vh; background: #090d16;
    }

    /* ===== TOPBAR ===== */
    .topbar {
        height: 70px;
        background: rgba(9,13,22,0.85);
        backdrop-filter: saturate(180%) blur(20px); -webkit-backdrop-filter: saturate(180%) blur(20px);
        border-bottom: 1px solid rgba(255,255,255,0.08);
        display: flex; align-items: center; justify-content: space-between;
        padding: 0 30px; position: sticky; top: 0; z-index: 90;
    }
    .topbar-left { display: flex; align-items: center; gap: 16px; }
    .topbar-toggle {
        display: none; background: none; border: 1px solid rgba(255,255,255,0.08);
        border-radius: 10px; padding: 8px; cursor: pointer;
        color: #64748b; transition: all 0.2s;
    }
    .topbar-toggle:hover { background: rgba(255,255,255,0.06); color: #fff; border-color: rgba(255,255,255,0.12); }
    .topbar-toggle svg { display: block; }
    .topbar-title h1 {
        font-size: 18px; font-weight: 800; color: #f8fafc;
        margin: 0; letter-spacing: -0.3px;
    }
    .topbar-title p {
        font-size: 12.5px; color: #94a3b8; margin: 3px 0 0 0;
        font-weight: 500;
    }
    .topbar-right { display: flex; align-items: center; gap: 12px; }
    .topbar-role-badge {
        background: rgba(37,99,235,0.1); color: #60a5fa;
        border: 1px solid rgba(37,99,235,0.2); padding: 6px 14px;
        border-radius: 9999px; font-size: 11.5px; font-weight: 700;
        letter-spacing: 0.3px; display: inline-flex; align-items: center; gap: 6px;
    }
    .topbar-role-badge::before {
        content: ''; width: 6px; height: 6px; border-radius: 50%;
        background: #2563eb; display: inline-block;
        box-shadow: 0 0 8px rgba(37,99,235,0.7);
    }
    .topbar-logout {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(239,68,68,0.07); color: #f87171;
        border: 1px solid rgba(239,68,68,0.18); padding: 7px 16px;
        border-radius: 10px; font-size: 13px; font-weight: 700;
        transition: all 0.2s cubic-bezier(0.4,0,0.2,1); cursor: pointer;
    }
    .topbar-logout:hover {
        background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.35);
        transform: translateY(-1px); color: #fca5a5;
        box-shadow: 0 4px 12px rgba(239,68,68,0.15);
    }
    .topbar-logout svg { width: 16px; height: 16px; }

    /* ===== CONTENT AREA ===== */
    .content { padding: 28px 30px; flex: 1; }

    /* ===== FLASH MESSAGES ===== */
    .flash-msg {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 18px; border-radius: 12px;
        margin-bottom: 20px; font-size: 14px; font-weight: 500;
        animation: flashSlide 0.35s ease;
    }
    .flash-msg svg { flex-shrink: 0; }
    @keyframes flashSlide {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .flash-success {
        background: rgba(16,185,129,0.08); color: #34d399;
        border: 1px solid rgba(16,185,129,0.2);
    }
    .flash-error {
        background: rgba(239,68,68,0.08); color: #f87171;
        border: 1px solid rgba(239,68,68,0.2);
    }

    /* ===== RESPONSIVE (MOBILE SIMULATOR & ALL SCREENS) ===== */
    .sidebar-close-btn {
        display: none;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        width: 32px;
        height: 32px;
        color: #cbd5e1;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        margin-left: auto;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .sidebar-close-btn:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.4);
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 280px;
            max-width: 85vw;
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: none;
            z-index: 1000;
        }
        .sidebar.active {
            transform: translateX(0);
            box-shadow: 10px 0 40px rgba(0,0,0,0.7);
        }
        .sidebar-close-btn { display: flex; }
        .main { margin-left: 0; min-width: 0; width: 100%; }
        .topbar-toggle { display: flex; }
        .content { padding: 20px 16px; }
        .overlay-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.7); z-index: 995;
            backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
            transition: opacity 0.3s;
        }
        .overlay-backdrop.active { display: block; }
    }

    @media (max-width: 640px) {
        html, body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }
        .app-layout, .main {
            overflow-x: hidden;
            width: 100%;
            min-width: 0;
        }
        .topbar {
            padding: 0 14px;
            height: 60px;
        }
        .topbar-left {
            gap: 10px;
            min-width: 0;
            flex: 1;
        }
        .topbar-toggle {
            width: 38px;
            height: 38px;
            padding: 7px;
            flex-shrink: 0;
        }
        .topbar-title {
            min-width: 0;
            overflow: hidden;
        }
        .topbar-title h1 {
            font-size: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .topbar-title p {
            display: none;
        }
        .topbar-role-badge { display: none; }
        .topbar-logout {
            padding: 6px 12px;
            font-size: 12px;
            flex-shrink: 0;
        }
        .content {
            padding: 16px 12px;
        }
    }

    @media (max-width: 380px) {
        .topbar { padding: 0 10px; }
        .topbar-title h1 { font-size: 14px; }
        .topbar-logout span { display: none; }
        .topbar-logout { padding: 7px 9px; }
        .content { padding: 12px 8px; }
    }
</style>
</head>
<body>
<div class="app-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-brand-icon">🅿️</div>
      <div class="sidebar-brand-text">
        <b><?= e($perusahaan['nama_perusahaan']) ?></b>
        <small>Smart Mobility · Panel <?= e($role_label) ?></small>
      </div>
      <button class="sidebar-close-btn" id="btnCloseSidebar" type="button" aria-label="Tutup menu">✕</button>
    </div>

    <div class="sidebar-profile">
      <div class="sidebar-avatar"><?= e($user_initial) ?></div>
      <div class="sidebar-profile-info">
        <b><?= e($_SESSION['nama_lengkap'] ?? 'User') ?></b>
        <span><span class="role-dot"></span> <?= e($role_label) ?></span>
      </div>
    </div>

    <ul class="sidebar-menu">
      <li class="sidebar-menu-section">Menu <?= strtoupper($role_label) ?></li>
      <?php foreach ($menu as $m): ?>
        <li>
          <a href="<?= $m['path'] . $m['file'] ?>" class="<?= $current === $m['file'] ? 'active' : '' ?>">
            <span class="sidebar-menu-icon"><?= $svg_icons[$m['icon']] ?? '' ?></span>
            <span><?= $m['label'] ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
      &copy; <?= date('Y') ?> <?= e($perusahaan['nama_perusahaan']) ?>
    </div>
  </aside>

  <div class="overlay-backdrop" id="overlay"></div>

  <div class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button class="topbar-toggle" id="btnToggleSidebar" aria-label="Toggle menu">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
        </button>
        <div class="topbar-title">
          <h1><?= e($page_title) ?></h1>
          <?php if ($page_subtitle): ?><p><?= e($page_subtitle) ?></p><?php endif; ?>
        </div>
      </div>
      <div class="topbar-right">
        <span class="topbar-role-badge"><?= e($role_label) ?></span>
        <a href="<?= BASE_URL ?>logout.php" class="topbar-logout" onclick="return confirm('Yakin ingin logout?')">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>Logout</span>
        </a>
      </div>
    </header>

    <main class="content">
      <?php if ($msg = flash('success')): ?>
        <div class="flash-msg flash-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <?= $msg ?>
        </div>
      <?php endif; ?>
      <?php if ($msg = flash('error')): ?>
        <div class="flash-msg flash-error">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          <?= $msg ?>
        </div>
      <?php endif; ?>
