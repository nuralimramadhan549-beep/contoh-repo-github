<?php
// ============================================================
// KELOLA OPERATOR - ADMIN (dengan Pagination)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Kelola Operator';
$page_subtitle = 'Tambah, edit, atau nonaktifkan operator';
$uid = user_id();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telp = trim($_POST['no_telepon'] ?? '');
        $password = $_POST['password'] ?? '';
        $shift = $_POST['shift'] ?? 'pagi';
        $errors = [];

        if (strlen($nama) < 3) $errors[] = 'Nama minimal 3 karakter.';
        if (!validasi_username($username)) $errors[] = 'Username tidak valid (4-30 huruf/angka/underscore).';
        if ($email && !validasi_email($email)) $errors[] = 'Email tidak valid.';
        if (!validasi_password($password)) $errors[] = 'Password minimal 6 karakter.';

        if (empty($errors)) {
            $stmt = $koneksi->prepare("SELECT 1 FROM users WHERE username=?");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $errors[] = 'Username sudah dipakai.';
        }
        if (empty($errors) && $email) {
            $stmt = $koneksi->prepare("SELECT 1 FROM users WHERE email=?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $errors[] = 'Email sudah terdaftar.';
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'operator';
            $stmt = $koneksi->prepare("INSERT INTO users (nama_lengkap, username, email, no_telepon, password, role, shift, aktif) VALUES (?,?,?,?,?,?,?,1)");
            $stmt->bind_param('sssssss', $nama, $username, $email, $telp, $hash, $role, $shift);
            if ($stmt->execute()) {
                log_aktivitas($koneksi, $uid, 'tambah_operator', "User: $username");
                flash('success', "Operator <b>$nama</b> berhasil ditambahkan.");
            } else {
                flash('error', 'Gagal: ' . $koneksi->error);
            }
            redirect(PATH_ADMIN . 'operator.php');
        } else {
            flash('error', implode('<br>', $errors));
        }
    }

    if ($aksi === 'toggle') {
        $id = (int)$_POST['id_user'];
        $koneksi->query("UPDATE users SET aktif = 1 - aktif WHERE id_user=$id AND role='operator'");
        log_aktivitas($koneksi, $uid, 'toggle_operator', "ID: $id");
        flash('success', 'Status operator diubah.');
        redirect(PATH_ADMIN . 'operator.php?page=' . (int)($_POST['redirect_page'] ?? 1));
    }

    if ($aksi === 'reset') {
        $id = (int)$_POST['id_user'];
        $hash = password_hash('operator123', PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("UPDATE users SET password=? WHERE id_user=? AND role='operator'");
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        log_aktivitas($koneksi, $uid, 'reset_operator_pass', "ID: $id");
        flash('success', "Password direset ke: <b>operator123</b>");
        redirect(PATH_ADMIN . 'operator.php?page=' . (int)($_POST['redirect_page'] ?? 1));
    }

    if ($aksi === 'hapus') {
        $id = (int)$_POST['id_user'];
        $koneksi->query("DELETE FROM users WHERE id_user=$id AND role='operator'");
        log_aktivitas($koneksi, $uid, 'hapus_operator', "ID: $id");
        flash('success', 'Operator dihapus.');
        redirect(PATH_ADMIN . 'operator.php?page=' . (int)($_POST['redirect_page'] ?? 1));
    }
}

// Search
$search = trim($_GET['q'] ?? '');
$where = "WHERE u.role='operator'";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
    $types = 'sss';
}

// Count
$count_sql = "SELECT COUNT(*) c FROM users u $where";
if (!empty($params)) {
    $stmt = $koneksi->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_data = (int)$stmt->get_result()->fetch_assoc()['c'];
} else {
    $total_data = (int)$koneksi->query($count_sql)->fetch_assoc()['c'];
}

$pagi = paginate($total_data, 10);

// Data
$sql = "SELECT u.*,
    (SELECT COUNT(*) FROM transaksi_parkir WHERE operator_id = u.id_user) AS total_input,
    (SELECT COUNT(*) FROM pembayaran WHERE kasir_id = u.id_user) AS total_kasir,
    (SELECT COUNT(*) FROM transaksi_parkir WHERE operator_id = u.id_user AND DATE(waktu_masuk) = CURDATE()) AS input_hari_ini
    FROM users u 
    $where 
    ORDER BY u.created_at DESC
    LIMIT {$pagi['per_page']} OFFSET {$pagi['offset']}";

if (!empty($params)) {
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $operator = $stmt->get_result();
} else {
    $operator = $koneksi->query($sql);
}

$total_all = (int)$koneksi->query("SELECT COUNT(*) c FROM users WHERE role='operator'")->fetch_assoc()['c'];
$aktif_count = (int)$koneksi->query("SELECT COUNT(*) c FROM users WHERE role='operator' AND aktif=1")->fetch_assoc()['c'];

include __DIR__ . '/../includes/header.php';
?>

<style>
html, body { overflow-x: hidden; }
.content { padding: 24px; }

.op-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.op-header-left h2 {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 4px 0;
  letter-spacing: -.3px;
}
.op-header-left p {
  font-size: 13.5px;
  color: #94a3b8;
  margin: 0;
}

.op-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}
.op-stat {
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
}
.op-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,.4); }
.op-stat.cyan  { border-left-color: #06b6d4; }
.op-stat.green { border-left-color: #10b981; }
.op-stat.red   { border-left-color: #ef4444; }

.op-stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.op-stat-icon {
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
.op-stat.cyan .op-stat-icon  { background: rgba(6,182,212,0.18); color: #38bdf8; border: 1px solid rgba(6,182,212,0.3); }
.op-stat.green .op-stat-icon { background: rgba(16,185,129,0.18); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
.op-stat.red .op-stat-icon   { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

.op-stat-label {
  font-size: 10.5px;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .6px;
}
.op-stat-value {
  font-size: 26px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1;
  letter-spacing: -.5px;
  font-family: 'JetBrains Mono', monospace;
}
.op-stat-desc { font-size: 11.5px; color: #94a3b8; }

.op-form-card {
  background: #111827;
  border-radius: 14px;
  padding: 22px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  margin-bottom: 20px;
}
.op-form-title {
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 18px 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.op-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.op-search {
  background: #111827;
  border-radius: 14px;
  padding: 16px 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  margin-bottom: 20px;
}
.op-search-form {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}
.op-search-input {
  flex: 1;
  min-width: 0;
  padding: 11px 14px;
  border: 1.5px solid rgba(255, 255, 255, 0.12);
  border-radius: 10px;
  font-size: 13.5px;
  font-family: inherit;
  transition: .2s;
  background: #090d16;
  color: #ffffff;
}
.op-search-input:focus {
  outline: none;
  border-color: #3b82f6;
  background: #0b0f19;
  box-shadow: 0 0 0 3px rgba(59,130,246,.25);
}

.op-table-card {
  background: #111827;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  overflow: hidden;
}
.op-table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.op-table-title {
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.op-table-info {
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  padding: 4px 12px;
  background: #090d16;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  white-space: nowrap;
}

.op-table-scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border-radius: 10px;
  max-width: 100%;
}
.op-table-scroll::-webkit-scrollbar { height: 8px; }
.op-table-scroll::-webkit-scrollbar-track { background: #090d16; border-radius: 10px; }
.op-table-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }

.op-table-scroll table {
  width: 100%;
  min-width: 820px;
  border-collapse: collapse;
  font-size: 13px;
}
.op-table-scroll thead th {
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
.op-table-scroll tbody td {
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  color: #f1f5f9;
  vertical-align: middle;
}
.op-table-scroll tbody tr:last-child td { border-bottom: none; }
.op-table-scroll tbody tr:hover { background: rgba(59, 130, 246, 0.06); }

.op-user { display: flex; align-items: center; gap: 10px; }
.op-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; color: #fff; font-size: 14px;
  flex-shrink: 0; box-shadow: 0 3px 8px rgba(0,0,0,.3);
}
.op-avatar.on  { background: linear-gradient(135deg, #10b981, #059669); }
.op-avatar.off { background: linear-gradient(135deg, #64748b, #475569); }
.op-user-info { min-width: 0; }
.op-user-name {
  font-size: 13.5px; font-weight: 700; color: #ffffff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 180px;
}
.op-user-sub { font-size: 11px; color: #94a3b8; }

.op-actions { display: flex; gap: 4px; justify-content: flex-end; }
.op-btn-icon {
  width: 32px; height: 32px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.08);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 14px; transition: all .2s; flex-shrink: 0; font-family: inherit;
}
.op-btn-icon:hover { transform: scale(1.1); }
.op-btn-icon.toggle { background: rgba(255, 255, 255, 0.08); color: #fff; }
.op-btn-icon.reset  { background: rgba(245, 158, 11, 0.18); border-color: rgba(245, 158, 11, 0.35); }
.op-btn-icon.del    { background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.35); }

.btn-add {
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
  font-family: inherit;
}
.btn-add:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(37,99,235,.5); }

@media (max-width: 900px) {
  .op-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .op-stat { padding: 14px 16px; }
  .op-stat-value { font-size: 22px; }
}
@media (max-width: 640px) {
  .content { padding: 16px; }
  .op-header-left h2 { font-size: 18px; }
  .op-stats { grid-template-columns: 1fr; }
  .op-stat { flex-direction: row; align-items: center; gap: 14px; }
  .op-stat-top { flex-direction: column; align-items: flex-start; gap: 8px; flex: 1; }
  .op-form-grid { grid-template-columns: 1fr; }
  .btn-add { width: 100%; justify-content: center; }
  .op-search-form { flex-direction: column; align-items: stretch; }
  .op-search-form .btn { width: 100%; }
}
</style>

<!-- Header -->
<div class="op-header">
  <div class="op-header-left">
    <h2>👨‍🔧 Kelola Operator</h2>
    <p>Total <b><?= number_format($total_all) ?></b> operator · <b><?= number_format($aktif_count) ?></b> aktif</p>
  </div>
  <button class="btn-add" onclick="toggleForm()" id="btnToggle">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Tambah Operator
  </button>
</div>

<!-- Stats -->
<div class="op-stats">
  <div class="op-stat cyan">
    <div class="op-stat-top">
      <div class="op-stat-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      </div>
      <span class="op-stat-label">Total</span>
    </div>
    <div class="op-stat-value"><?= number_format($total_all) ?></div>
    <div class="op-stat-desc">Operator terdaftar</div>
  </div>

  <div class="op-stat green">
    <div class="op-stat-top">
      <div class="op-stat-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <span class="op-stat-label">Aktif</span>
    </div>
    <div class="op-stat-value"><?= number_format($aktif_count) ?></div>
    <div class="op-stat-desc">Dapat login</div>
  </div>

  <div class="op-stat red">
    <div class="op-stat-top">
      <div class="op-stat-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
      </div>
      <span class="op-stat-label">Nonaktif</span>
    </div>
    <div class="op-stat-value"><?= number_format($total_all - $aktif_count) ?></div>
    <div class="op-stat-desc">Tidak dapat login</div>
  </div>
</div>

<!-- Form Tambah (toggle) -->
<div class="op-form-card" id="formTambah" style="display:none;">
  <h3 class="op-form-title">➕ Tambah Operator Baru</h3>
  <form method="post">
    <input type="hidden" name="aksi" value="tambah">
    <div class="op-form-grid">
      <div class="form-group">
        <label>👤 Nama Lengkap *</label>
        <input type="text" name="nama_lengkap" required placeholder="Budi Santoso">
      </div>
      <div class="form-group">
        <label>🔤 Username *</label>
        <input type="text" name="username" required placeholder="budi_op">
      </div>
      <div class="form-group">
        <label>✉️ Email</label>
        <input type="email" name="email" placeholder="budi@parkirbogor.co.id">
      </div>
      <div class="form-group">
        <label>📱 No. Telepon</label>
        <input type="text" name="no_telepon" placeholder="08123456789">
      </div>
      <div class="form-group">
        <label>🔒 Password * (min 6)</label>
        <input type="password" name="password" required placeholder="operator123">
      </div>
      <div class="form-group">
        <label>⏰ Shift</label>
        <select name="shift">
          <option value="pagi">Pagi (06-14)</option>
          <option value="siang">Siang (14-22)</option>
          <option value="malam">Malam (22-06)</option>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="submit" class="btn btn-success">💾 Simpan</button>
      <button type="button" class="btn btn-secondary" onclick="toggleForm()">Batal</button>
    </div>
  </form>
</div>

<!-- Search -->
<div class="op-search">
  <form method="get" class="op-search-form">
    <input type="text" name="q" class="op-search-input" 
           placeholder="🔍 Cari nama, username, atau email operator..." 
           value="<?= e($search) ?>">
    <button type="submit" class="btn btn-primary">Cari</button>
    <?php if ($search): ?>
      <a href="<?= PATH_ADMIN ?>operator.php" class="btn btn-secondary">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Table -->
<div class="op-table-card">
  <div class="op-table-header">
    <h3 class="op-table-title">📋 Daftar Operator</h3>
    <span class="op-table-info">
      <?php if ($search): ?>
        Hasil: <b><?= number_format($total_data) ?></b>
      <?php else: ?>
        Total: <b><?= number_format($total_all) ?></b>
      <?php endif; ?>
    </span>
  </div>

  <?php if ($operator->num_rows === 0): ?>
    <div style="text-align:center;padding:48px 20px;color:#94a3b8;">
      <div style="font-size:48px;margin-bottom:12px;opacity:.5;"><?= $search ? '🔍' : '👨‍🔧' ?></div>
      <div style="font-size:14px;font-weight:600;">
        <?= $search ? 'Tidak ada hasil untuk "' . e($search) . '"' : 'Belum ada operator terdaftar' ?>
      </div>
    </div>
  <?php else: ?>
    <div class="op-table-scroll">
      <table>
        <thead>
          <tr>
            <th>Operator</th>
            <th>Kontak</th>
            <th>Shift</th>
            <th>Total Input</th>
            <th>Login Terakhir</th>
            <th>Status</th>
            <th style="text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($r = $operator->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="op-user">
                  <div class="op-avatar <?= $r['aktif'] ? 'on' : 'off' ?>">
                    <?= strtoupper(substr($r['nama_lengkap'], 0, 1)) ?>
                  </div>
                  <div class="op-user-info">
                    <div class="op-user-name" title="<?= e($r['nama_lengkap']) ?>">
                      <?= e($r['nama_lengkap']) ?>
                    </div>
                    <div class="op-user-sub">@<?= e($r['username']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-size:12.5px;"><?= e($r['email'] ?: '-') ?></div>
                <div style="font-size:11px;color:#94a3b8;"><?= e($r['no_telepon'] ?: '-') ?></div>
              </td>
              <td><span class="badge badge-info"><?= e($r['shift'] ?: '-') ?></span></td>
              <td>
                <b><?= number_format($r['total_input']) ?></b>
                <?php if ($r['input_hari_ini'] > 0): ?>
                  <span class="badge badge-success" style="font-size:10px;">+<?= $r['input_hari_ini'] ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['last_login']): ?>
                  <div style="font-size:12px;"><?= date('d/m', strtotime($r['last_login'])) ?></div>
                  <div style="font-size:11px;color:#94a3b8;"><?= date('H:i', strtotime($r['last_login'])) ?></div>
                <?php else: ?>
                  <span style="color:#94a3b8;">Belum login</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['aktif']): ?>
                  <span class="badge badge-success">Aktif</span>
                <?php else: ?>
                  <span class="badge badge-danger">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <form method="post" class="op-actions">
                  <input type="hidden" name="id_user" value="<?= $r['id_user'] ?>">
                  <input type="hidden" name="redirect_page" value="<?= $pagi['page'] ?>">
                  <button class="op-btn-icon toggle" name="aksi" value="toggle" title="Toggle aktif"><?= $r['aktif'] ? '🚫' : '✅' ?></button>
                  <button class="op-btn-icon reset" name="aksi" value="reset" title="Reset password" onclick="return confirm('Reset password ke operator123?')">🔑</button>
                  <button class="op-btn-icon del" name="aksi" value="hapus" title="Hapus" onclick="return confirm('Yakin hapus?')">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?= render_pagination($pagi) ?>
  <?php endif; ?>
</div>

<script>
function toggleForm() {
  const f = document.getElementById('formTambah');
  const b = document.getElementById('btnToggle');
  const show = f.style.display === 'none';
  f.style.display = show ? 'block' : 'none';
  if (show) {
    f.scrollIntoView({ behavior: 'smooth', block: 'start' });
    b.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Tutup Form';
  } else {
    b.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Operator';
  }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>