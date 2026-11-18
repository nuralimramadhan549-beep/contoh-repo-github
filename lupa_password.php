<?php
// ============================================================
// RESET / LUPA KATA SANDI - PT. PARKIR BOGOR (ENTERPRISE)
// Mengizinkan pengguna (Admin, Operator, Pelanggan) mereset
// kata sandi dengan verifikasi akun aman & antarmuka Obsidian Dark
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_login()) {
    redirect_dashboard();
}

// Reset session jika user ingin ganti akun
if (isset($_GET['action']) && $_GET['action'] === 'batal') {
    unset($_SESSION['reset_user']);
    redirect(BASE_URL . 'lupa_password.php');
}

// Validasi timeout sesi reset (15 menit)
if (isset($_SESSION['reset_user'])) {
    if (time() - ($_SESSION['reset_user']['verified_at'] ?? 0) > 900) {
        unset($_SESSION['reset_user']);
        flash('error', 'Sesi verifikasi telah kedaluwarsa. Silakan verifikasi akun kembali.');
        redirect(BASE_URL . 'lupa_password.php');
    }
}

$reset_user = $_SESSION['reset_user'] ?? null;
$error = '';
$success = '';

// Helper masking email
function mask_email_display($email) {
    if (!$email || strpos($email, '@') === false) return '******';
    $parts = explode('@', $email, 2);
    $user = $parts[0];
    $domain = $parts[1];
    $len = strlen($user);
    if ($len <= 2) {
        $masked = substr($user, 0, 1) . '*';
    } else {
        $masked = substr($user, 0, 2) . str_repeat('*', min(4, $len - 2)) . substr($user, -1);
    }
    return $masked . '@' . $domain;
}

// PROSES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '1';

    if ($step === '1') {
        // TAHAP 1: VERIFIKASI IDENTITAS AKUN
        $identitas = trim($_POST['identitas'] ?? '');
        $no_telepon = trim($_POST['no_telepon'] ?? '');

        if ($identitas === '') {
            $error = 'Silakan masukkan username atau alamat email terdaftar.';
        } else {
            $stmt = $koneksi->prepare(
                "SELECT id_user, nama_lengkap, username, email, no_telepon, role, aktif 
                 FROM users 
                 WHERE (username = ? OR email = ?) 
                 LIMIT 1"
            );
            $stmt->bind_param('ss', $identitas, $identitas);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = 'Akun dengan username atau email tersebut tidak ditemukan.';
            } elseif ((int)$user['aktif'] !== 1) {
                $error = 'Akun ini dinonaktifkan oleh administrator. Silakan hubungi petugas.';
            } else {
                // Jika input nomor telepon disertakan, verifikasi kesesuaian
                if ($no_telepon !== '') {
                    $clean_input_phone = preg_replace('/[^0-9]/', '', $no_telepon);
                    $clean_user_phone  = preg_replace('/[^0-9]/', '', $user['no_telepon'] ?? '');
                    if ($clean_input_phone !== '' && $clean_user_phone !== '' && $clean_input_phone !== $clean_user_phone) {
                        $error = 'Nomor telepon tidak cocok dengan nomor terdaftar pada akun ini.';
                    }
                }

                if (empty($error)) {
                    $_SESSION['reset_user'] = [
                        'id_user'      => (int)$user['id_user'],
                        'nama_lengkap' => $user['nama_lengkap'],
                        'username'     => $user['username'],
                        'email'        => $user['email'] ?? '',
                        'no_telepon'   => $user['no_telepon'] ?? '',
                        'role'         => $user['role'],
                        'verified_at'  => time()
                    ];
                    redirect(BASE_URL . 'lupa_password.php');
                }
            }
        }
    } elseif ($step === '2') {
        // TAHAP 2: SIMPAN KATA SANDI BARU
        if (!$reset_user) {
            $error = 'Sesi tidak valid. Silakan mulai kembali dari tahap verifikasi.';
        } else {
            $password_baru = $_POST['password_baru'] ?? '';
            $password_konf = $_POST['password_konfirmasi'] ?? '';

            if ($password_baru === '') {
                $error = 'Kata sandi baru wajib diisi.';
            } elseif (strlen($password_baru) < 6) {
                $error = 'Kata sandi baru minimal harus 6 karakter.';
            } elseif ($password_baru !== $password_konf) {
                $error = 'Konfirmasi kata sandi baru tidak sesuai.';
            } else {
                // Hash dan update kata sandi
                $hash = password_hash($password_baru, PASSWORD_DEFAULT);
                $uid  = (int)$reset_user['id_user'];

                $stmt = $koneksi->prepare("UPDATE users SET password = ? WHERE id_user = ? LIMIT 1");
                $stmt->bind_param('si', $hash, $uid);

                if ($stmt->execute()) {
                    $stmt->close();

                    log_aktivitas($koneksi, $uid, 'reset_password', 'Kata sandi diubah melalui halaman Reset Password mandiri');

                    unset($_SESSION['reset_user']);
                    flash('success', 'Kata sandi akun "' . $reset_user['username'] . '" berhasil diperbarui! Silakan masuk dengan kata sandi baru.');
                    redirect(BASE_URL . 'login.php');
                } else {
                    $error = 'Terjadi kesalahan saat menyimpan kata sandi. Silakan coba kembali: ' . $koneksi->error;
                }
            }
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
<title>Reset Kata Sandi — <?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?></title>
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
.reset-card {
  width: 100%; max-width: 480px; background: #0f172a;
  border: 1px solid rgba(255,255,255,.12); border-radius: 24px;
  padding: 40px 36px;
  box-shadow: 0 30px 70px rgba(0,0,0,.6), 0 0 30px rgba(37,99,235,.15);
  position: relative; z-index: 10;
}
.brand-header {
  display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 24px;
}
.brand-icon {
  width: 54px; height: 54px; border-radius: 14px;
  background: linear-gradient(135deg, #2563eb, #4f46e5);
  display: flex; align-items: center; justify-content: center;
  font-size: 26px; color: #fff;
  box-shadow: 0 8px 24px rgba(37,99,235,.4);
  margin-bottom: 14px; border: 1px solid rgba(255,255,255,.2);
}
.brand-header h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 22px; font-weight: 800; color: #fff;
  letter-spacing: -.5px; margin: 0 0 4px; line-height: 1.25;
}
.brand-header p {
  font-size: 13px; color: #94a3b8; margin: 0;
}

/* Stepper Bar */
.stepper {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px; padding: 10px 14px; background: #0b1220;
  border: 1px solid #1e293b; border-radius: 12px;
}
.step-item {
  display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600;
  color: #64748b;
}
.step-item.active {
  color: #38bdf8;
}
.step-item.completed {
  color: #4ade80;
}
.step-bubble {
  width: 22px; height: 22px; border-radius: 50%;
  background: #1e293b; color: #94a3b8; display: flex; align-items: center;
  justify-content: center; font-size: 11px; font-weight: 700;
}
.step-item.active .step-bubble {
  background: #2563eb; color: #fff; box-shadow: 0 0 10px rgba(37,99,235,.6);
}
.step-item.completed .step-bubble {
  background: #166534; color: #4ade80;
}
.step-arrow {
  color: #334155; font-size: 12px;
}

/* Account Preview Card (Step 2) */
.user-preview {
  display: flex; align-items: center; justify-content: space-between;
  background: #0b1220; border: 1px solid #1e293b; border-radius: 14px;
  padding: 14px 16px; margin-bottom: 22px;
}
.user-preview-info {
  display: flex; align-items: center; gap: 12px;
}
.user-avatar {
  width: 42px; height: 42px; border-radius: 10px;
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 17px; color: #fff;
  border: 1px solid rgba(255,255,255,.15);
}
.user-name {
  font-weight: 700; font-size: 14px; color: #f8fafc; margin: 0 0 2px;
}
.user-meta {
  font-size: 12px; color: #94a3b8; margin: 0; display: flex; gap: 8px; align-items: center;
}
.user-badge {
  font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 6px;
  text-transform: uppercase; letter-spacing: .04em;
}
.badge-admin { background: rgba(147, 51, 234, .2); color: #c084fc; border: 1px solid rgba(168, 85, 247, .3); }
.badge-operator { background: rgba(2, 132, 199, .2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, .3); }
.badge-pelanggan { background: rgba(16, 185, 129, .2); color: #34d399; border: 1px solid rgba(52, 211, 153, .3); }

.btn-change-account {
  font-size: 11px; font-weight: 600; color: #f87171; text-decoration: none;
  background: rgba(239, 68, 68, .1); border: 1px solid rgba(239, 68, 68, .2);
  padding: 6px 10px; border-radius: 8px; transition: background .15s, color .15s;
}
.btn-change-account:hover {
  background: rgba(239, 68, 68, .2); color: #fca5a5;
}

/* Forms */
.form-group {
  display: flex; flex-direction: column; margin-bottom: 18px; width: 100%;
}
.form-group > label {
  display: block; margin-bottom: 6px; font-size: 12px;
  font-weight: 600; color: #cbd5e1; letter-spacing: .02em; line-height: 1.4;
}
.input-with-icon {
  position: relative; width: 100%;
}
.input-with-icon svg.field-icon {
  position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
  color: #64748b; pointer-events: none; width: 18px; height: 18px; z-index: 2;
}
.input-with-icon input {
  display: block; width: 100%; box-sizing: border-box; height: 46px;
  padding: 0 44px 0 42px; background: #0b1220; border: 1.5px solid #1e293b;
  color: #fff; border-radius: 10px; font-size: 14px; font-family: inherit;
  line-height: 1.4; outline: none;
  transition: border-color .15s, background .15s, box-shadow .15s;
  -webkit-appearance: none; appearance: none;
}
.input-with-icon input::placeholder { color: #475569; }
.input-with-icon input:hover { border-color: #334155; }
.input-with-icon input:focus {
  border-color: #2563eb; background: #020617;
  box-shadow: 0 0 0 3px rgba(37,99,235,.25);
}
.toggle-password-btn {
  position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; background: transparent; border: none;
  border-radius: 8px; color: #64748b; cursor: pointer; padding: 0; z-index: 2;
  transition: color .15s, background .15s;
}
.toggle-password-btn:hover { color: #fff; background: rgba(255,255,255,.06); }

/* Password Strength Indicator */
.pwd-strength-container {
  margin-top: 8px;
}
.pwd-strength-bar {
  display: flex; gap: 4px; height: 4px; width: 100%; margin-bottom: 6px;
}
.pwd-strength-seg {
  flex: 1; height: 100%; background: #1e293b; border-radius: 2px;
  transition: background .2s ease;
}
.pwd-strength-text {
  font-size: 11px; color: #94a3b8; display: flex; justify-content: space-between;
}
.pwd-checklist {
  font-size: 11px; color: #64748b; margin-top: 6px; line-height: 1.4;
}

/* Match status */
.match-status {
  font-size: 11px; margin-top: 5px; font-weight: 500;
}
.match-ok { color: #4ade80; }
.match-error { color: #f87171; }

/* Buttons */
.btn-primary-action {
  display: block; width: 100%; height: 48px; margin-top: 22px; padding: 0 16px;
  background: linear-gradient(135deg, #2563eb, #4f46e5); color: #fff;
  border: none; border-radius: 10px; font-family: inherit; font-size: 14px;
  font-weight: 700; letter-spacing: .01em; cursor: pointer;
  transition: transform .1s, box-shadow .15s, filter .15s;
  box-shadow: 0 8px 20px rgba(37,99,235,.3);
}
.btn-primary-action:hover {
  filter: brightness(1.08); box-shadow: 0 10px 26px rgba(37,99,235,.45);
}
.btn-primary-action:active { transform: translateY(1px); }

/* Alerts */
.alert {
  padding: 11px 14px; border-radius: 10px; margin-bottom: 18px;
  font-size: 13px; line-height: 1.5; border: 1px solid transparent;
}
.alert-success {
  background: rgba(22,101,52,.15); color: #4ade80; border-color: rgba(74,222,128,.3);
}
.alert-danger {
  background: rgba(153,27,27,.15); color: #f87171; border-color: rgba(248,113,113,.3);
}

/* Info Callout */
.info-callout {
  background: rgba(37,99,235,.08); border: 1px solid rgba(37,99,235,.25);
  border-radius: 10px; padding: 12px 14px; margin-bottom: 18px;
  font-size: 12px; color: #93c5fd; line-height: 1.5;
  display: flex; gap: 10px; align-items: flex-start;
}
.info-callout svg {
  width: 18px; height: 18px; flex-shrink: 0; color: #60a5fa; margin-top: 1px;
}

/* Footer Links */
.auth-footer {
  text-align: center; margin-top: 24px; padding-top: 18px;
  border-top: 1px solid rgba(255,255,255,.08); font-size: 13px; color: #94a3b8;
}
.auth-footer a.btn-back-login {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 10px 18px; background: #1e293b; border: 1px solid #334155;
  border-radius: 10px; color: #f1f5f9; text-decoration: none; font-weight: 600;
  font-size: 13px; transition: background .15s, border-color .15s; width: 100%;
}
.auth-footer a.btn-back-login:hover {
  background: #273449; border-color: #475569;
}
.auth-links {
  display: flex; justify-content: center; align-items: center;
  gap: 10px; flex-wrap: wrap; font-size: 12px; color: #64748b; margin-top: 16px;
}
.auth-links a {
  color: #94a3b8; text-decoration: none; transition: color .15s;
}
.auth-links a:hover {
  color: #38bdf8;
}

@media (max-width: 480px) {
  body { padding: 16px; }
  .reset-card { padding: 30px 20px; border-radius: 18px; }
  .brand-header h1 { font-size: 20px; }
}
</style>
</head>
<body>

<div class="reset-card">
  <!-- Brand Header -->
  <div class="brand-header">
    <div class="brand-icon" aria-hidden="true">🔑</div>
    <h1>Reset Kata Sandi</h1>
    <p><?= e($perusahaan['nama_perusahaan'] ?? 'PT. Parkir Bogor') ?> · Pemulihan Akses</p>
  </div>

  <!-- Stepper Indicator -->
  <div class="stepper" aria-label="Progress reset password">
    <div class="step-item <?= !$reset_user ? 'active' : 'completed' ?>">
      <div class="step-bubble"><?= $reset_user ? '✓' : '1' ?></div>
      <span>1. Verifikasi Akun</span>
    </div>
    <div class="step-arrow">➔</div>
    <div class="step-item <?= $reset_user ? 'active' : '' ?>">
      <div class="step-bubble">2</div>
      <span>2. Buat Sandi Baru</span>
    </div>
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

  <?php if (!$reset_user): ?>
    <!-- ========================================== -->
    <!-- TAHAP 1: IDENTIFIKASI & VERIFIKASI AKUN    -->
    <!-- ========================================== -->
    <div class="info-callout">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="16" x2="12" y2="12"/>
        <line x1="12" y1="8" x2="12.01" y2="8"/>
      </svg>
      <div>
        Masukkan <strong>Username</strong> atau <strong>Email</strong> Anda. Sistem akan mencari akun terdaftar Anda untuk mengatur ulang kata sandi.
      </div>
    </div>

    <form method="post" novalidate>
      <input type="hidden" name="step" value="1">

      <div class="form-group">
        <label for="inputIdentitas">Username atau Alamat Email</label>
        <div class="input-with-icon">
          <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <input id="inputIdentitas" type="text" name="identitas"
                 autocomplete="username" required autofocus
                 value="<?= e($_POST['identitas'] ?? '') ?>"
                 placeholder="Contoh: ahmad atau ahmad@gmail.com">
        </div>
      </div>

      <div class="form-group">
        <label for="inputTelepon">Nomor Telepon Terdaftar <span style="color:#64748b; font-weight:normal;">(Opsional verifikasi)</span></label>
        <div class="input-with-icon">
          <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
          </svg>
          <input id="inputTelepon" type="tel" name="no_telepon"
                 value="<?= e($_POST['no_telepon'] ?? '') ?>"
                 placeholder="Contoh: 08123456789">
        </div>
      </div>

      <button type="submit" class="btn-primary-action">🔍 Verifikasi & Lanjutkan</button>
    </form>

  <?php else: ?>
    <!-- ========================================== -->
    <!-- TAHAP 2: ATUR ULANG KATA SANDI BARU        -->
    <!-- ========================================== -->
    <div class="user-preview">
      <div class="user-preview-info">
        <div class="user-avatar" aria-hidden="true">
          <?= strtoupper(substr($reset_user['nama_lengkap'] ?? $reset_user['username'], 0, 1)) ?>
        </div>
        <div>
          <div class="user-name"><?= e($reset_user['nama_lengkap']) ?></div>
          <div class="user-meta">
            <span>@<?= e($reset_user['username']) ?></span>
            <span aria-hidden="true">·</span>
            <span class="user-badge badge-<?= e($reset_user['role']) ?>"><?= ucfirst(e($reset_user['role'])) ?></span>
          </div>
        </div>
      </div>
      <a href="?action=batal" class="btn-change-account" title="Bukan akun ini? Batalkan verifikasi">Ganti Akun</a>
    </div>

    <form method="post" novalidate id="formResetPassword">
      <input type="hidden" name="step" value="2">

      <div class="form-group">
        <label for="inputPasswordBaru">Kata Sandi Baru</label>
        <div class="input-with-icon">
          <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          <input id="inputPasswordBaru" type="password" name="password_baru"
                 autocomplete="new-password" required autofocus
                 placeholder="Minimal 6 karakter baru">
          <button type="button" class="toggle-password-btn"
                  data-target="inputPasswordBaru"
                  aria-label="Tampilkan kata sandi" aria-pressed="false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>

        <!-- Strength Indicator -->
        <div class="pwd-strength-container">
          <div class="pwd-strength-bar">
            <div class="pwd-strength-seg" id="seg1"></div>
            <div class="pwd-strength-seg" id="seg2"></div>
            <div class="pwd-strength-seg" id="seg3"></div>
            <div class="pwd-strength-seg" id="seg4"></div>
          </div>
          <div class="pwd-strength-text">
            <span>Kekuatan sandi: <strong id="strengthLabel" style="color:#94a3b8;">Belum diisi</strong></span>
            <span id="strengthAdvice">Min. 6 karakter</span>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label for="inputPasswordKonfirmasi">Konfirmasi Kata Sandi Baru</label>
        <div class="input-with-icon">
          <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
          <input id="inputPasswordKonfirmasi" type="password" name="password_konfirmasi"
                 autocomplete="new-password" required
                 placeholder="Ulangi kata sandi baru">
          <button type="button" class="toggle-password-btn"
                  data-target="inputPasswordKonfirmasi"
                  aria-label="Tampilkan kata sandi" aria-pressed="false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div id="matchMessage" class="match-status"></div>
      </div>

      <button type="submit" class="btn-primary-action">💾 Simpan & Perbarui Kata Sandi</button>
    </form>
  <?php endif; ?>

  <!-- Footer Actions -->
  <div class="auth-footer">
    <a href="<?= BASE_URL ?>login.php" class="btn-back-login">
      ← Kembali ke Halaman Login
    </a>
    <div class="auth-links">
      <a href="<?= BASE_URL ?>register.php">📝 Daftar Akun</a>
      <span aria-hidden="true">·</span>
      <a href="<?= BASE_URL ?>kiosk.php" target="_blank" rel="noopener">🖥️ Kiosk Mandiri</a>
      <span aria-hidden="true">·</span>
      <a href="<?= BASE_URL ?>index.php">🌐 Beranda Utama</a>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  // Toggle Visibility Password
  var toggleBtns = document.querySelectorAll('.toggle-password-btn');
  var ICON_EYE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  var ICON_EYE_OFF = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

  toggleBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-target');
      var input = document.getElementById(targetId);
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? ICON_EYE_OFF : ICON_EYE;
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
      input.focus();
    });
  });

  // Password Strength & Match Validation (Step 2)
  var pwdInput = document.getElementById('inputPasswordBaru');
  var confInput = document.getElementById('inputPasswordKonfirmasi');
  var segs = [
    document.getElementById('seg1'),
    document.getElementById('seg2'),
    document.getElementById('seg3'),
    document.getElementById('seg4')
  ];
  var strengthLabel = document.getElementById('strengthLabel');
  var matchMsg = document.getElementById('matchMessage');

  if (pwdInput) {
    pwdInput.addEventListener('input', function () {
      var val = pwdInput.value;
      var score = 0;

      if (val.length >= 6) score++;
      if (val.length >= 8) score++;
      if (/[0-9]/.test(val) && /[a-zA-Z]/.test(val)) score++;
      if (/[^a-zA-Z0-9]/.test(val)) score++;

      // Reset bars
      segs.forEach(function (s) { if (s) s.style.background = '#1e293b'; });

      if (val.length === 0) {
        if (strengthLabel) {
          strengthLabel.textContent = 'Belum diisi';
          strengthLabel.style.color = '#94a3b8';
        }
      } else if (val.length < 6) {
        if (segs[0]) segs[0].style.background = '#ef4444';
        if (strengthLabel) {
          strengthLabel.textContent = 'Terlalu Pendek (< 6)';
          strengthLabel.style.color = '#ef4444';
        }
      } else if (score === 1) {
        if (segs[0]) segs[0].style.background = '#f97316';
        if (strengthLabel) {
          strengthLabel.textContent = 'Cukup Lemah';
          strengthLabel.style.color = '#f97316';
        }
      } else if (score === 2) {
        if (segs[0]) segs[0].style.background = '#eab308';
        if (segs[1]) segs[1].style.background = '#eab308';
        if (strengthLabel) {
          strengthLabel.textContent = 'Sedang';
          strengthLabel.style.color = '#eab308';
        }
      } else if (score === 3) {
        if (segs[0]) segs[0].style.background = '#3b82f6';
        if (segs[1]) segs[1].style.background = '#3b82f6';
        if (segs[2]) segs[2].style.background = '#3b82f6';
        if (strengthLabel) {
          strengthLabel.textContent = 'Kuat';
          strengthLabel.style.color = '#3b82f6';
        }
      } else {
        segs.forEach(function (s) { if (s) s.style.background = '#10b981'; });
        if (strengthLabel) {
          strengthLabel.textContent = 'Sangat Kuat 🔥';
          strengthLabel.style.color = '#10b981';
        }
      }

      checkMatch();
    });
  }

  if (confInput) {
    confInput.addEventListener('input', checkMatch);
  }

  function checkMatch() {
    if (!confInput || !pwdInput || !matchMsg) return;
    var p1 = pwdInput.value;
    var p2 = confInput.value;

    if (p2.length === 0) {
      matchMsg.textContent = '';
      matchMsg.className = 'match-status';
      return;
    }

    if (p1 === p2) {
      matchMsg.textContent = '✓ Kata sandi cocok!';
      matchMsg.className = 'match-status match-ok';
    } else {
      matchMsg.textContent = '✗ Konfirmasi kata sandi belum cocok.';
      matchMsg.className = 'match-status match-error';
    }
  }
})();
</script>
</body>
</html>
