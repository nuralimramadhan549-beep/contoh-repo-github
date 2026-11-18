<?php
// ============================================================
// KONFIGURASI DATABASE - PT. PARKIR BOGOR (ENTERPRISE EDITION)
// Standard: Auto-migration & Multi-Role Connection Handler
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

// Kredensial Database (Mendukung Environment Variable untuk Hosting/Cloud & Fallback ke Localhost XAMPP)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'db_parkir_bogor');
define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);

// Konfigurasi Aplikasi & Deteksi Otomatis Base URL
define('APP_NAME', 'PT. Parkir Bogor');
define('APP_VERSION', '3.2');

if (!defined('BASE_URL')) {
    $auto_base = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/parkir-bogor/') !== false) 
        ? '/parkir-bogor/' 
        : '/';
    define('BASE_URL', getenv('BASE_URL') ?: $auto_base);
}

// Path Subfolder
define('PATH_PUBLIC',   BASE_URL . 'public/');
define('PATH_OPERATOR', BASE_URL . 'operator/');
define('PATH_ADMIN',    BASE_URL . 'admin/');

// Matikan unhandled exception default mysqli agar ditangani secara elegan & ramah pengguna
mysqli_report(MYSQLI_REPORT_OFF);

// ============================================================
// HANDLER DIAGNOSTIK KONEKSI DATABASE (JIKA MYSQL OFFLINE)
// ============================================================
if (!function_exists('tampilkan_error_koneksi_db')) {
    function tampilkan_error_koneksi_db($errno, $error_msg, $host, $port, $dbname) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Koneksi Database Terputus — <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'PT. Parkir Bogor') ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
        <style>
          *, *::before, *::after { box-sizing: border-box; }
          body {
            margin: 0; padding: 24px; min-height: 100vh;
            background: #090d16; color: #e2e8f0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            display: flex; align-items: center; justify-content: center;
            position: relative; overflow-x: hidden;
          }
          body::before {
            content: ''; position: fixed; top: -120px; left: -120px;
            width: 450px; height: 450px; border-radius: 50%;
            background: radial-gradient(circle, rgba(239, 68, 68, .2) 0%, transparent 70%);
            pointer-events: none;
          }
          body::after {
            content: ''; position: fixed; bottom: -120px; right: -120px;
            width: 450px; height: 450px; border-radius: 50%;
            background: radial-gradient(circle, rgba(37, 99, 235, .15) 0%, transparent 70%);
            pointer-events: none;
          }
          .error-card {
            width: 100%; max-width: 580px; background: #0f172a;
            border: 1px solid rgba(239, 68, 68, .3); border-radius: 20px;
            padding: 36px 32px; box-shadow: 0 25px 60px rgba(0,0,0,.6), 0 0 30px rgba(239, 68, 68, .15);
            position: relative; z-index: 10;
          }
          .error-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(239, 68, 68, .15); border: 1px solid rgba(239, 68, 68, .4);
            color: #f87171; padding: 6px 14px; border-radius: 999px;
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
            margin-bottom: 20px;
          }
          .pulse-dot {
            width: 8px; height: 8px; border-radius: 50%; background: #ef4444;
            box-shadow: 0 0 8px #ef4444; animation: pulse 1.5s infinite;
          }
          @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: .4; transform: scale(.8); } }
          h1 {
            font-family: 'Plus Jakarta Sans', sans-serif; font-size: 24px; font-weight: 800;
            color: #ffffff; margin: 0 0 10px; letter-spacing: -.02em;
          }
          p.lead {
            font-size: 14px; color: #94a3b8; line-height: 1.6; margin: 0 0 24px;
          }
          .steps-box {
            background: #0b1220; border: 1px solid #1e293b; border-radius: 12px;
            padding: 18px 20px; margin-bottom: 24px;
          }
          .steps-box h3 {
            margin: 0 0 12px; font-size: 13px; font-weight: 700; color: #38bdf8;
            text-transform: uppercase; letter-spacing: .04em;
          }
          .step-list {
            margin: 0; padding-left: 20px; font-size: 13px; color: #cbd5e1; line-height: 1.7;
          }
          .step-list li strong { color: #f8fafc; }
          .step-list code {
            background: #1e293b; padding: 2px 6px; border-radius: 4px;
            font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #fbbf24;
          }
          .actions {
            display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 12px;
          }
          .btn-reload {
            flex: 1; min-width: 200px; height: 46px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff;
            font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 6px 18px rgba(37, 99, 235, .35);
            transition: filter .15s, transform .1s;
          }
          .btn-reload:hover { filter: brightness(1.1); }
          .btn-reload:active { transform: translateY(1px); }
          .countdown-note {
            width: 100%; text-align: center; font-size: 12px; color: #64748b; margin-top: 4px;
          }
          details {
            border-top: 1px solid rgba(255,255,255,.08); padding-top: 16px; margin-top: 20px;
          }
          summary {
            font-size: 12px; font-weight: 600; color: #64748b; cursor: pointer; user-select: none;
          }
          summary:hover { color: #94a3b8; }
          .tech-details {
            margin-top: 10px; background: #070b14; border: 1px solid #1e293b;
            border-radius: 8px; padding: 12px 14px; font-family: 'JetBrains Mono', monospace;
            font-size: 11px; color: #f87171; line-height: 1.6; word-break: break-all;
          }
        </style>
        </head>
        <body>
          <div class="error-card">
            <div class="error-badge">
              <span class="pulse-dot"></span>
              Database MySQL Offline
            </div>
            <h1>Layanan Database Tidak Terhubung</h1>
            <p class="lead">
              Aplikasi <strong>PT. Parkir Bogor</strong> tidak dapat menghubungi server database MySQL di <code><?= htmlspecialchars($host . ':' . $port) ?></code>. Masalah ini biasanya terjadi karena layanan <strong>MySQL di XAMPP belum dinyalakan</strong> atau terhenti setelah komputer dinyalakan ulang.
            </p>
            
            <div class="steps-box">
              <h3>💡 Cara Mengatasi Cepat:</h3>
              <ol class="step-list">
                <li>Buka aplikasi <strong>XAMPP Control Panel</strong> di komputer Anda.</li>
                <li>Pada modul baris <strong>MySQL</strong>, klik tombol <strong>"Start"</strong>.</li>
                <li>Pastikan tulisan <strong>MySQL</strong> berubah latar belakang menjadi warna <strong>hijau</strong> (Port <code>3306</code>).</li>
                <li>Klik tombol <strong>"Hubungkan Ulang Sekarang"</strong> di bawah.</li>
              </ol>
            </div>

            <div class="actions">
              <button class="btn-reload" onclick="window.location.reload();">
                🔄 Hubungkan Ulang Sekarang
              </button>
            </div>
            <div class="countdown-note">
              Mengecek ulang otomatis dalam <strong id="cd" style="color:#38bdf8;">10</strong> detik...
            </div>

            <details>
              <summary>Detail Teknis / Diagnostic Logs</summary>
              <div class="tech-details">
                [Errno <?= (int)$errno ?>] <?= htmlspecialchars($error_msg) ?><br>
                Target: <?= htmlspecialchars($host . ':' . $port) ?> (Database: <?= htmlspecialchars($dbname) ?>)<br>
                PHP Version: <?= PHP_VERSION ?> | OS: <?= PHP_OS ?>
              </div>
            </details>
          </div>

          <script>
            var sec = 10;
            var cd = document.getElementById('cd');
            var timer = setInterval(function () {
              sec--;
              if (cd) cd.textContent = sec;
              if (sec <= 0) {
                clearInterval(timer);
                window.location.reload();
              }
            }, 1000);
          </script>
        </body>
        </html>
        <?php
    }
}

// ============================================================
// INISIALISASI KONEKSI DATABASE
// ============================================================
$koneksi = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Jika port default 3306 connection refused (2002), coba port alternatif 3307
if ($koneksi->connect_errno === 2002) {
    $koneksi_alt = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);
    if (!$koneksi_alt->connect_errno) {
        $koneksi = $koneksi_alt;
    }
}

// Jika database db_parkir_bogor belum dibuat (error 1049: Unknown database)
if ($koneksi->connect_errno === 1049) {
    $koneksi_root = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    if (!$koneksi_root->connect_errno) {
        $koneksi_root->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $koneksi_root->close();
        $koneksi = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    }
}

// Jika masih gagal terkoneksi (MySQL offline / refused)
if ($koneksi->connect_errno) {
    tampilkan_error_koneksi_db($koneksi->connect_errno, $koneksi->connect_error, DB_HOST, DB_PORT, DB_NAME);
    exit;
}

$koneksi->set_charset('utf8mb4');

// ============================================================
// AUTO-SCHEMA SYNC (Memastikan Kolom & Tabel Selalu Kompatibel)
// ============================================================
if (($_SESSION['db_schema_version'] ?? 0) < 2) {
    try {
        // 1. users: role enum with pelanggan, email, no_telepon, alamat
        $colsUsers = [];
        $res = $koneksi->query("SHOW COLUMNS FROM users");
        if ($res) {
            while ($c = $res->fetch_assoc()) $colsUsers[] = $c['Field'];
            if (!in_array('email', $colsUsers)) $koneksi->query("ALTER TABLE users ADD COLUMN email VARCHAR(100) DEFAULT NULL AFTER nama_lengkap");
            if (!in_array('no_telepon', $colsUsers)) $koneksi->query("ALTER TABLE users ADD COLUMN no_telepon VARCHAR(20) DEFAULT NULL AFTER email");
            if (!in_array('alamat', $colsUsers)) $koneksi->query("ALTER TABLE users ADD COLUMN alamat TEXT DEFAULT NULL AFTER no_telepon");
            $koneksi->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','operator','pelanggan') NOT NULL DEFAULT 'operator'");
        }

        // 2. kendaraan_pelanggan
        $koneksi->query("CREATE TABLE IF NOT EXISTS kendaraan_pelanggan (
          id_kendaraan INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          plat_nomor VARCHAR(15) NOT NULL,
          jenis_kendaraan ENUM('motor','mobil') NOT NULL,
          merk VARCHAR(50) DEFAULT NULL,
          warna VARCHAR(30) DEFAULT NULL,
          is_default TINYINT(1) NOT NULL DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user (user_id),
          INDEX idx_plat (plat_nomor)
        )");

        $colsKend = [];
        $resK = $koneksi->query("SHOW COLUMNS FROM kendaraan_pelanggan");
        if ($resK) {
            while ($c = $resK->fetch_assoc()) $colsKend[] = $c['Field'];
            if (!in_array('is_default', $colsKend)) {
                $koneksi->query("ALTER TABLE kendaraan_pelanggan ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER warna");
            }
        }

        // 3. transaksi_parkir: user_id, sumber, disetujui_oleh, waktu_ajuan_keluar, waktu_persetujuan, status
        $colsTrx = [];
        $res = $koneksi->query("SHOW COLUMNS FROM transaksi_parkir");
        if ($res) {
            while ($c = $res->fetch_assoc()) $colsTrx[] = $c['Field'];
            if (!in_array('user_id', $colsTrx)) $koneksi->query("ALTER TABLE transaksi_parkir ADD COLUMN user_id INT DEFAULT NULL AFTER plat_nomor");
            if (!in_array('disetujui_oleh', $colsTrx)) $koneksi->query("ALTER TABLE transaksi_parkir ADD COLUMN disetujui_oleh INT DEFAULT NULL AFTER operator_id");
            if (!in_array('sumber', $colsTrx)) $koneksi->query("ALTER TABLE transaksi_parkir ADD COLUMN sumber ENUM('operator','kiosk','pelanggan') NOT NULL DEFAULT 'operator' AFTER kode_tiket");
            if (!in_array('waktu_ajuan_keluar', $colsTrx)) $koneksi->query("ALTER TABLE transaksi_parkir ADD COLUMN waktu_ajuan_keluar DATETIME DEFAULT NULL AFTER waktu_masuk");
            if (!in_array('waktu_persetujuan', $colsTrx)) $koneksi->query("ALTER TABLE transaksi_parkir ADD COLUMN waktu_persetujuan DATETIME DEFAULT NULL AFTER waktu_ajuan_keluar");
            $koneksi->query("ALTER TABLE transaksi_parkir MODIFY COLUMN status ENUM('aktif','menunggu_approval','selesai','batal') NOT NULL DEFAULT 'aktif'");
        }

        // 4. Views
        $koneksi->query("CREATE OR REPLACE VIEW v_transaksi_aktif AS
          SELECT t.*, s.kode_slot, s.zona, u.nama_lengkap AS operator, up.nama_lengkap AS pelanggan_nama
          FROM transaksi_parkir t
          JOIN slot_parkir s ON t.id_slot = s.id_slot
          LEFT JOIN users u ON t.operator_id = u.id_user
          LEFT JOIN users up ON t.user_id = up.id_user
          WHERE t.waktu_keluar IS NULL AND t.status IN ('aktif', 'menunggu_approval')");

        $koneksi->query("CREATE OR REPLACE VIEW v_ringkasan_hari_ini AS
          SELECT
            (SELECT COUNT(*) FROM transaksi_parkir WHERE DATE(waktu_masuk)=CURDATE()) AS masuk_hari_ini,
            (SELECT COUNT(*) FROM transaksi_parkir WHERE waktu_keluar IS NULL AND status IN ('aktif', 'menunggu_approval')) AS masih_parkir,
            (SELECT COALESCE(SUM(total_biaya + IFNULL(denda,0)),0) FROM pembayaran WHERE DATE(waktu_bayar)=CURDATE()) AS pendapatan_hari_ini,
            (SELECT COUNT(*) FROM slot_parkir WHERE status='kosong') AS slot_kosong,
            (SELECT COUNT(*) FROM slot_parkir WHERE status='terisi') AS slot_terisi,
            (SELECT COUNT(*) FROM slot_parkir) AS slot_total");

        $koneksi->query("CREATE OR REPLACE VIEW v_statistik_operator AS
          SELECT 
            u.id_user, u.nama_lengkap, u.username, u.shift, u.aktif,
            COUNT(t.id_transaksi) AS total_input,
            COUNT(p.id_pembayaran) AS total_checkout,
            COALESCE(SUM(p.total_biaya + IFNULL(p.denda,0)), 0) AS total_penerimaan
          FROM users u
          LEFT JOIN transaksi_parkir t ON t.operator_id = u.id_user
          LEFT JOIN pembayaran p ON p.kasir_id = u.id_user
          WHERE u.role = 'operator'
          GROUP BY u.id_user");

        $koneksi->query("CREATE OR REPLACE VIEW v_statistik_pelanggan AS
          SELECT 
            u.id_user, u.nama_lengkap, u.username,
            COUNT(DISTINCT t.id_transaksi) AS total_transaksi,
            COALESCE(SUM(p.total_biaya + IFNULL(p.denda,0)), 0) AS total_bayar
          FROM users u
          LEFT JOIN transaksi_parkir t ON t.user_id = u.id_user
          LEFT JOIN pembayaran p ON p.id_transaksi = t.id_transaksi
          WHERE u.role = 'pelanggan'
          GROUP BY u.id_user");
    } catch (Exception $e) {}
    $_SESSION['db_schema_version'] = 2;
    $_SESSION['db_schema_synced'] = true;
}

// Ambil info perusahaan
if (!isset($_SESSION['perusahaan_cache'])) {
    $res = $koneksi->query("SELECT * FROM perusahaan LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $_SESSION['perusahaan_cache'] = $res->fetch_assoc();
    } else {
        $_SESSION['perusahaan_cache'] = [
            'nama_perusahaan' => 'PT. Parkir Bogor',
            'alamat' => 'Jl. Pajajaran No. 1, Bogor',
            'telepon' => '0251-123456',
            'jam_operasional' => '24 Jam'
        ];
    }
}
$perusahaan = $_SESSION['perusahaan_cache'];
?>
