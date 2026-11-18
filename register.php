<?php
// ============================================================
// REGISTER PELANGGAN - PT. PARKIR BOGOR (ENTERPRISE EDITION)
// Modern & International Design Standards
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_login()) {
    redirect_dashboard();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $no_telepon   = trim($_POST['no_telepon'] ?? '');
    $alamat       = trim($_POST['alamat'] ?? '');
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['password2'] ?? '';

    if ($nama_lengkap === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    } elseif (strlen($nama_lengkap) < 3) {
        $errors[] = 'Nama lengkap minimal 3 karakter.';
    }

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    } elseif (!validasi_username($username)) {
        $errors[] = 'Username hanya boleh huruf/angka/underscore (4-30 karakter).';
    }

    if ($email === '') {
        $errors[] = 'Email wajib diisi.';
    } elseif (!validasi_email($email)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($no_telepon === '') {
        $errors[] = 'No. telepon wajib diisi.';
    } elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $no_telepon)) {
        $errors[] = 'No. telepon tidak valid (8-20 digit).';
    }

    if ($password === '') {
        $errors[] = 'Kata sandi wajib diisi.';
    } elseif (!validasi_password($password)) {
        $errors[] = 'Kata sandi minimal 6 karakter.';
    }

    if ($password !== $password2) {
        $errors[] = 'Konfirmasi kata sandi tidak cocok.';
    }

    if (empty($errors)) {
        $stmt = $koneksi->prepare("SELECT id_user FROM users WHERE username=? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username sudah digunakan oleh pengguna lain.';
        }
    }

    if (empty($errors)) {
        $stmt = $koneksi->prepare("SELECT id_user FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email sudah terdaftar dalam sistem.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'pelanggan';

        $stmt = $koneksi->prepare("INSERT INTO users 
            (nama_lengkap, username, email, no_telepon, alamat, password, role, aktif) 
            VALUES (?,?,?,?,?,?,?,1)");
        $stmt->bind_param('sssssss',
            $nama_lengkap, $username, $email, $no_telepon, $alamat, $hash, $role);

        if ($stmt->execute()) {
            $id_user = $koneksi->insert_id;
            log_aktivitas($koneksi, $id_user, 'register', "Pelanggan baru terdaftar: $username");

            $_SESSION['id_user']       = $id_user;
            $_SESSION['nama_lengkap']  = $nama_lengkap;
            $_SESSION['username']      = $username;
            $_SESSION['email']         = $email;
            $_SESSION['role']          = 'pelanggan';
            $_SESSION['last_activity'] = time();

            flash('success', "Selamat datang, $nama_lengkap! Akun pelanggan Anda berhasil dibuat.");
            redirect(PATH_PUBLIC . 'dashboard.php');
        } else {
            $errors[] = 'Gagal mendaftar: ' . $koneksi->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrasi Pelanggan — <?= e($perusahaan['nama_perusahaan']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">
<style>
body {
  background: #090d16;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 36px 20px;
  position: relative;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: absolute;
  top: -150px; right: -150px;
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(37, 99, 235, 0.22) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

body::after {
  content: '';
  position: absolute;
  bottom: -150px; left: -150px;
  width: 550px; height: 550px;
  background: radial-gradient(circle, rgba(6, 182, 212, 0.16) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

.reg-card {
  width: 100%;
  max-width: 540px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 24px;
  padding: 44px 38px;
  box-shadow: 0 30px 70px rgba(0, 0, 0, 0.6), 0 0 30px rgba(37, 99, 235, 0.12);
  position: relative;
  z-index: 10;
}

.reg-brand {
  text-align: center;
  margin-bottom: 28px;
}

.reg-brand-icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg, #06b6d4, #0284c7);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  color: #fff;
  font-weight: 900;
  box-shadow: 0 8px 24px rgba(6, 182, 212, 0.35);
  margin: 0 auto 14px;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.reg-brand h1 {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  letter-spacing: -0.5px;
  margin-bottom: 4px;
}

.reg-brand p {
  font-size: 13px;
  color: #94a3b8;
}

.reg-card input,
.reg-card textarea {
  background: #090d16;
  border: 1.5px solid #1e293b;
  color: #ffffff;
  border-radius: 10px;
}

.reg-card input:focus,
.reg-card textarea:focus {
  border-color: #06b6d4;
  background: #020617;
  box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.18);
}

.reg-card label {
  color: #cbd5e1;
  font-size: 12.5px;
}

.reg-footer {
  text-align: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  font-size: 13px;
  color: #94a3b8;
}

.reg-footer a {
  color: #38bdf8;
  font-weight: 600;
}
.reg-footer a:hover {
  text-decoration: underline;
}

@media (max-width: 540px) {
  .reg-card { padding: 32px 22px; }
  .form-row { grid-template-columns: 1fr; gap: 0; }
}
</style>
</head>
<body>

<div class="reg-card">
  <div class="reg-brand">
    <div class="reg-brand-icon">📝</div>
    <h1>Registrasi Pelanggan Baru</h1>
    <p>Bergabung dengan ekosistem parkir cerdas <?= e($perusahaan['nama_perusahaan']) ?></p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <span class="alert-icon">✕</span>
      <div>
        <?php foreach ($errors as $err): ?>
          <div>• <?= e($err) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <form method="post" autocomplete="off">
    <div class="form-group">
      <label>Nama Lengkap <span style="color:#ef4444;">*</span></label>
      <input type="text" name="nama_lengkap" required autofocus
             value="<?= e($_POST['nama_lengkap'] ?? '') ?>"
             placeholder="Contoh: Budi Pratama">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Username <span style="color:#ef4444;">*</span></label>
        <input type="text" name="username" required
               value="<?= e($_POST['username'] ?? '') ?>"
               placeholder="budipratama">
      </div>
      <div class="form-group">
        <label>No. Handphone / WA <span style="color:#ef4444;">*</span></label>
        <input type="text" name="no_telepon" required
               value="<?= e($_POST['no_telepon'] ?? '') ?>"
               placeholder="08123456789">
      </div>
    </div>

    <div class="form-group">
      <label>Alamat Email <span style="color:#ef4444;">*</span></label>
      <input type="email" name="email" required
             value="<?= e($_POST['email'] ?? '') ?>"
             placeholder="budi@example.com">
    </div>

    <div class="form-group">
      <label>Alamat Domisili (Opsional)</label>
      <textarea name="alamat" rows="2" placeholder="Nama jalan, perumahan, atau kota"><?= e($_POST['alamat'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label>Kata Sandi <span style="color:#ef4444;">*</span></label>
        <input type="password" name="password" required placeholder="Min. 6 karakter">
      </div>
      <div class="form-group">
        <label>Konfirmasi Sandi <span style="color:#ef4444;">*</span></label>
        <input type="password" name="password2" required placeholder="Ulangi sandi">
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:14px;background:linear-gradient(135deg, #0284c7, #2563eb);">
      Daftar & Masuk ke Dashboard
    </button>
  </form>

  <div class="reg-footer">
    <p style="margin-bottom:12px;">Sudah memiliki akun terdaftar?</p>
    <a href="login.php" class="btn btn-secondary btn-block" style="background:#1e293b;border-color:#334155;color:#ffffff;margin-bottom:14px;">
      🔐 Login ke Akun Anda
    </a>

    <div style="font-size:12px;display:flex;justify-content:center;gap:14px;color:#64748b;margin-top:16px;">
      <a href="lupa_password.php" style="color:#94a3b8;text-decoration:none;">🔑 Lupa Kata Sandi?</a>
      <span>·</span>
      <a href="index.php" style="color:#94a3b8;text-decoration:none;">← Kembali ke Beranda</a>
    </div>
  </div>
</div>

<script src="assets/app.js?v=<?= time() ?>"></script>
</body>
</html>