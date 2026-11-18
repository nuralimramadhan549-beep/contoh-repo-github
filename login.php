<?php
// ============================================================
// LOGIN MULTI-ROLE - PT. PARKIR BOGOR
// Final version — struktur users terkonfirmasi cocok
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_login()) {
    redirect_dashboard();
}

$error = '';
if (isset($_GET['timeout'])) {
    $error = 'Sesi Anda berakhir karena tidak aktif. Silakan login kembali.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare(
            "SELECT id_user, username, password, nama_lengkap, email, role, aktif 
             FROM users WHERE username = ? LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'Username atau password tidak cocok!';
        } elseif ((int)$user['aktif'] !== 1) {
            $error = 'Akun Anda dinonaktifkan. Hubungi administrator.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Username atau password tidak cocok!';
        } else {
            // Login berhasil
            session_regenerate_id(true);

            $_SESSION['id_user']       = (int)$user['id_user'];
            $_SESSION['nama_lengkap']  = $user['nama_lengkap'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();

            $upd = $koneksi->prepare(
                "UPDATE users SET last_login = NOW() WHERE id_user = ? LIMIT 1"
            );
            $upd->bind_param('i', $user['id_user']);
            $upd->execute();
            $upd->close();

            log_aktivitas($koneksi, $user['id_user'], 'login', "Login sebagai {$user['role']}");

            redirect_dashboard();
        }
    }
}

$flash_msg = flash('success');
$flash_err = flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Portal — <?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/style.css?v=<?= time() ?>">
<style>
*, *::before, *::after { box-sizing: border-box; }
html, body { margin: 0; padding: 0; min-height: 100%; }
body {
  background: #090d16; min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 24px; position: relative; overflow-x: hidden;
  font-family: 'Inter', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
  color: #e2e8f0;
}
body::before, body::after {
  content: ''; position: fixed; border-radius: 50%; pointer-events: none; z-index: 0;
}
body::before {
  top: -150px; left: -150px; width: 550px; height: 550px;
  background: radial-gradient(circle, rgba(37,99,235,.25) 0%, transparent 70%);
}
body::after {
  bottom: -150px; right: -150px; width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(6,182,212,.18) 0%, transparent 70%);
}
.login-card {
  width: 100%; max-width: 440px; background: #0f172a;
  border: 1px solid rgba(255,255,255,.12); border-radius: 24px;
  padding: 44px 38px;
  box-shadow: 0 30px 70px rgba(0,0,0,.6), 0 0 30px rgba(37,99,235,.15);
  position: relative; z-index: 10;
}
.login-brand { display:flex; flex-direction:column; align-items:center; text-align:center; margin-bottom:28px; }
.login-brand-icon {
  width:54px; height:54px; border-radius:14px;
  background: linear-gradient(135deg,#2563eb,#4f46e5);
  display:flex; align-items:center; justify-content:center;
  font-size:26px; color:#fff; font-weight:900;
  box-shadow:0 8px 24px rgba(37,99,235,.4);
  margin-bottom:16px; border:1px solid rgba(255,255,255,.2);
}
.login-brand h1 {
  font-family:'Plus Jakarta Sans','Inter',sans-serif;
  font-size:22px; font-weight:800; color:#fff;
  letter-spacing:-.5px; margin:0 0 4px; line-height:1.25;
}
.login-brand p { font-size:13px; color:#94a3b8; margin:0; }

.login-card .form-group { display:flex; flex-direction:column; margin-bottom:18px; width:100%; }
.login-card .form-group:last-of-type { margin-bottom:0; }
.login-card .form-group > label {
  display:block; margin-bottom:6px; font-size:12px;
  font-weight:600; color:#cbd5e1; letter-spacing:.02em; line-height:1.4;
}
.login-card .label-row {
  display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;
}
.login-card .label-row label {
  font-size:12px; font-weight:600; color:#cbd5e1; letter-spacing:.02em; line-height:1.4; margin:0;
}
.login-card .label-row .forgot-link {
  font-size:12px; color:#38bdf8; text-decoration:none; font-weight:600;
  transition:color .15s;
}
.login-card .label-row .forgot-link:hover {
  color:#7dd3fc; text-decoration:underline;
}

.login-card .input-with-icon { position:relative; width:100%; }
.login-card .input-with-icon svg.field-icon {
  position:absolute; left:14px; top:50%; transform:translateY(-50%);
  color:#64748b; pointer-events:none; width:18px; height:18px; z-index:2;
}
.login-card .input-with-icon input {
  display:block; width:100%; box-sizing:border-box; height:46px;
  padding:0 44px 0 42px; background:#0b1220; border:1.5px solid #1e293b;
  color:#fff; border-radius:10px; font-size:14px; font-family:inherit;
  line-height:1.4; outline:none;
  transition:border-color .15s,background .15s,box-shadow .15s;
  -webkit-appearance:none; appearance:none;
}
.login-card .input-with-icon input::placeholder { color:#475569; }
.login-card .input-with-icon input:hover { border-color:#334155; }
.login-card .input-with-icon input:focus {
  border-color:#2563eb; background:#020617;
  box-shadow:0 0 0 3px rgba(37,99,235,.25);
}
.login-card .toggle-password-btn {
  position:absolute; right:8px; top:50%; transform:translateY(-50%);
  display:inline-flex; align-items:center; justify-content:center;
  width:34px; height:34px; background:transparent; border:none;
  border-radius:8px; color:#64748b; cursor:pointer; padding:0; z-index:2;
  transition:color .15s,background .15s;
}
.login-card .toggle-password-btn:hover { color:#fff; background:rgba(255,255,255,.06); }
.login-card .toggle-password-btn:focus-visible { outline:2px solid #2563eb; outline-offset:2px; }

.login-card .btn-login {
  display:block; width:100%; height:48px; margin-top:24px; padding:0 16px;
  background: linear-gradient(135deg,#2563eb,#4f46e5); color:#fff;
  border:none; border-radius:10px; font-family:inherit; font-size:14px;
  font-weight:700; letter-spacing:.01em; cursor:pointer;
  transition:transform .1s,box-shadow .15s,filter .15s;
  box-shadow:0 8px 20px rgba(37,99,235,.3);
}
.login-card .btn-login:hover { filter:brightness(1.08); box-shadow:0 10px 26px rgba(37,99,235,.45); }
.login-card .btn-login:active { transform:translateY(1px); }
.login-card .btn-login:focus-visible { outline:2px solid #38bdf8; outline-offset:3px; }

.login-card .alert {
  padding:10px 12px; border-radius:8px; margin-bottom:16px;
  font-size:13px; line-height:1.5; border:1px solid transparent;
}
.login-card .alert-success {
  background:rgba(22,101,52,.15); color:#4ade80;
  border-color:rgba(74,222,128,.3);
}
.login-card .alert-danger {
  background:rgba(153,27,27,.15); color:#f87171;
  border-color:rgba(248,113,113,.3);
}

.login-card .auth-footer {
  text-align:center; margin-top:26px; padding-top:20px;
  border-top:1px solid rgba(255,255,255,.08); font-size:13px; color:#94a3b8;
}
.login-card .auth-footer p { margin:0 0 12px; }
.login-card .auth-footer .btn-register {
  display:block; padding:12px; background:#1e293b;
  border:1px solid #334155; border-radius:10px; color:#fff;
  margin-bottom:14px; text-decoration:none; font-weight:600;
  transition:background .15s,border-color .15s;
}
.login-card .auth-footer .btn-register:hover { background:#273449; border-color:#475569; }
.login-card .auth-footer .auth-links {
  display:flex; justify-content:center; align-items:center;
  gap:10px; flex-wrap:wrap; font-size:12px; color:#64748b; margin-top:16px;
}
.login-card .auth-footer .auth-links a { color:#94a3b8; text-decoration:none; transition:color .15s; }
.login-card .auth-footer .auth-links a:hover { color:#38bdf8; }

@media (max-width:480px) {
  body { padding:16px; }
  .login-card { padding:32px 22px; border-radius:18px; }
  .login-brand h1 { font-size:19px; }
}
</style>
</head>
<body>

<div class="login-card">
  <div class="login-brand">
    <div class="login-brand-icon" aria-hidden="true">🅿️</div>
    <h1><?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?></h1>
    <p>Sistem Manajemen Parkir Cerdas · Portal Masuk</p>
  </div>

  <?php if ($flash_msg): ?>
    <div class="alert alert-success" role="status"><?= e($flash_msg) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert alert-danger" role="alert"><?= e($flash_err) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" novalidate>
    <div class="form-group">
      <label for="loginUsername">Username Pengguna</label>
      <div class="input-with-icon">
        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        <input id="loginUsername" type="text" name="username"
               autocomplete="username" required autofocus spellcheck="false"
               value="<?= e($_POST['username'] ?? '') ?>"
               placeholder="Masukkan username Anda">
      </div>
    </div>

    <div class="form-group">
      <div class="label-row">
        <label for="loginPassword">Kata Sandi</label>
        <a href="<?= BASE_URL ?>lupa_password.php" class="forgot-link">Lupa kata sandi?</a>
      </div>
      <div class="input-with-icon">
        <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <input id="loginPassword" type="password" name="password"
               autocomplete="current-password" required
               placeholder="Masukkan kata sandi">
        <button type="button" class="toggle-password-btn"
                aria-label="Tampilkan kata sandi" aria-pressed="false"
                aria-controls="loginPassword">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-login">Masuk ke Sistem</button>
  </form>

  <div class="auth-footer">
    <p>Belum memiliki akun pelanggan?</p>
    <a href="<?= BASE_URL ?>register.php" class="btn-register">📝 Registrasi Akun Pelanggan</a>
    <div class="auth-links">
      <a href="<?= BASE_URL ?>lupa_password.php">🔑 Lupa Kata Sandi?</a>
      <span aria-hidden="true">·</span>
      <a href="<?= BASE_URL ?>kiosk.php" target="_blank" rel="noopener">🖥️ Kiosk Mandiri</a>
      <span aria-hidden="true">·</span>
      <a href="<?= BASE_URL ?>index.php">← Kembali ke Beranda</a>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var toggleBtn = document.querySelector('.toggle-password-btn');
  var pwdInput  = document.getElementById('loginPassword');
  if (!toggleBtn || !pwdInput) return;
  var ICON_EYE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  var ICON_EYE_OFF = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
  toggleBtn.addEventListener('click', function () {
    var show = pwdInput.type === 'password';
    pwdInput.type = show ? 'text' : 'password';
    toggleBtn.innerHTML = show ? ICON_EYE_OFF : ICON_EYE;
    toggleBtn.setAttribute('aria-pressed', show ? 'true' : 'false');
    toggleBtn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    pwdInput.focus();
  });
})();
</script>
</body>
</html>
