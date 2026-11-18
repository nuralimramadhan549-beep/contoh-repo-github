<?php
// ============================================================
// KELOLA PELANGGAN - ADMIN (dengan Pagination)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Kelola Pelanggan';
$page_subtitle = 'Daftar & kelola pelanggan terdaftar';
$uid = user_id();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $id = (int)($_POST['id_user'] ?? 0);

    if ($aksi === 'toggle') {
        $koneksi->query("UPDATE users SET aktif = 1 - aktif WHERE id_user=$id AND role='pelanggan'");
        log_aktivitas($koneksi, $uid, 'toggle_pelanggan', "ID: $id");
        flash('success', 'Status pelanggan diubah.');
    } elseif ($aksi === 'hapus') {
        $koneksi->query("DELETE FROM users WHERE id_user=$id AND role='pelanggan'");
        log_aktivitas($koneksi, $uid, 'hapus_pelanggan', "ID: $id");
        flash('success', 'Pelanggan dihapus.');
    } elseif ($aksi === 'reset') {
        $hash = password_hash('pelanggan123', PASSWORD_DEFAULT);
        $stmt = $koneksi->prepare("UPDATE users SET password=? WHERE id_user=? AND role='pelanggan'");
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        log_aktivitas($koneksi, $uid, 'reset_pelanggan_pass', "ID: $id");
        flash('success', 'Password direset ke: <b>pelanggan123</b>');
    }
    redirect(PATH_ADMIN . 'pelanggan.php' . (isset($_POST['redirect_page']) ? '?page=' . (int)$_POST['redirect_page'] : ''));
}

// Search
$search = trim($_GET['q'] ?? '');
$where = "WHERE role='pelanggan'";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (nama_lengkap LIKE ? OR username LIKE ? OR email LIKE ? OR no_telepon LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s];
    $types = 'ssss';
}

// Count total untuk pagination
$count_sql = "SELECT COUNT(*) c FROM users u $where";
if (!empty($params)) {
    $stmt = $koneksi->prepare($count_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total_data = (int)$stmt->get_result()->fetch_assoc()['c'];
} else {
    $total_data = (int)$koneksi->query($count_sql)->fetch_assoc()['c'];
}

// Pagination
$pagi = paginate($total_data, 10);

// Query data dengan LIMIT
$sql = "SELECT u.*, 
    (SELECT COUNT(*) FROM kendaraan_pelanggan WHERE user_id=u.id_user) AS total_kendaraan,
    (SELECT COUNT(*) FROM transaksi_parkir WHERE user_id=u.id_user) AS total_transaksi,
    (SELECT COALESCE(SUM(p.total_biaya + p.denda), 0) FROM transaksi_parkir t 
        LEFT JOIN pembayaran p ON p.id_transaksi=t.id_transaksi 
        WHERE t.user_id=u.id_user) AS total_bayar
    FROM users u $where ORDER BY u.created_at DESC
    LIMIT {$pagi['per_page']} OFFSET {$pagi['offset']}";

if (!empty($params)) {
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $pelanggan = $stmt->get_result();
} else {
    $pelanggan = $koneksi->query($sql);
}

// Statistik global (bukan per page)
$total_all = (int)$koneksi->query("SELECT COUNT(*) c FROM users WHERE role='pelanggan'")->fetch_assoc()['c'];
$aktif = (int)$koneksi->query("SELECT COUNT(*) c FROM users WHERE role='pelanggan' AND aktif=1")->fetch_assoc()['c'];

include __DIR__ . '/../includes/header.php';
?>

<style>
html, body { overflow-x: hidden; }
.content { padding: 24px; }

.pl-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.pl-header-left { min-width: 0; flex: 1; }
.pl-header-left h2 {
  font-size: 22px;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 4px 0;
  letter-spacing: -.3px;
}
.pl-header-left p {
  font-size: 13.5px;
  color: #94a3b8;
  margin: 0;
}

.pl-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}
.pl-stat {
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
  overflow: hidden;
}
.pl-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,.4); }
.pl-stat.cyan  { border-left-color: #06b6d4; }
.pl-stat.green { border-left-color: #10b981; }
.pl-stat.red   { border-left-color: #ef4444; }

.pl-stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.pl-stat-icon {
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
.pl-stat.cyan .pl-stat-icon  { background: rgba(6,182,212,0.18); color: #38bdf8; border: 1px solid rgba(6,182,212,0.3); }
.pl-stat.green .pl-stat-icon { background: rgba(16,185,129,0.18); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
.pl-stat.red .pl-stat-icon   { background: rgba(239,68,68,0.18); color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

.pl-stat-label {
  font-size: 10.5px;
  font-weight: 800;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: .6px;
}
.pl-stat-value {
  font-size: 26px;
  font-weight: 800;
  color: #ffffff;
  line-height: 1;
  letter-spacing: -.5px;
  font-family: 'JetBrains Mono', monospace;
}
.pl-stat-desc { font-size: 11.5px; color: #94a3b8; }

.pl-search {
  background: #111827;
  border-radius: 14px;
  padding: 16px 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  margin-bottom: 20px;
}
.pl-search-form {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}
.pl-search-input {
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
.pl-search-input:focus {
  outline: none;
  border-color: #3b82f6;
  background: #0b0f19;
  box-shadow: 0 0 0 3px rgba(59,130,246,.25);
}

.pl-table-card {
  background: #111827;
  border-radius: 14px;
  padding: 20px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  overflow: hidden;
  min-width: 0;
}
.pl-table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.pl-table-title {
  font-size: 15px;
  font-weight: 800;
  color: #ffffff;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 8px;
}
.pl-table-info {
  font-size: 12px;
  font-weight: 600;
  color: #94a3b8;
  padding: 4px 12px;
  background: #090d16;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 20px;
  white-space: nowrap;
}

.pl-table-scroll {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border-radius: 10px;
  max-width: 100%;
}
.pl-table-scroll::-webkit-scrollbar { height: 8px; }
.pl-table-scroll::-webkit-scrollbar-track { background: #090d16; border-radius: 10px; }
.pl-table-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
.pl-table-scroll::-webkit-scrollbar-thumb:hover { background: #475569; }

.pl-table-scroll table {
  width: 100%;
  min-width: 780px;
  border-collapse: collapse;
  font-size: 13px;
}
.pl-table-scroll thead th {
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
.pl-table-scroll tbody td {
  padding: 12px 14px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  color: #f1f5f9;
  vertical-align: middle;
}
.pl-table-scroll tbody tr:last-child td { border-bottom: none; }
.pl-table-scroll tbody tr:hover { background: rgba(59, 130, 246, 0.06); }

.pl-user { display: flex; align-items: center; gap: 10px; min-width: 0; }
.pl-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800; color: #fff; font-size: 14px;
  flex-shrink: 0; box-shadow: 0 3px 8px rgba(0,0,0,.3);
}
.pl-avatar.on  { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.pl-avatar.off { background: linear-gradient(135deg, #64748b, #475569); }
.pl-user-info { min-width: 0; }
.pl-user-name {
  font-size: 13.5px; font-weight: 700; color: #ffffff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 180px;
}
.pl-user-sub { font-size: 11px; color: #94a3b8; }

.pl-actions { display: flex; gap: 4px; flex-wrap: nowrap; justify-content: flex-end; }
.pl-btn-icon {
  width: 32px; height: 32px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.08);
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 14px; transition: all .2s; flex-shrink: 0; font-family: inherit;
}
.pl-btn-icon:hover { transform: scale(1.1); }
.pl-btn-icon.toggle { background: rgba(255, 255, 255, 0.08); color: #fff; }
.pl-btn-icon.reset  { background: rgba(245, 158, 11, 0.18); border-color: rgba(245, 158, 11, 0.35); }
.pl-btn-icon.del    { background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.35); }

.pl-empty { text-align: center; padding: 48px 20px; color: #94a3b8; }
.pl-empty-icon { font-size: 48px; margin-bottom: 12px; opacity: .5; }
.pl-empty-text { font-size: 14px; font-weight: 600; }

@media (max-width: 900px) {
  .pl-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
  .pl-stat { padding: 14px 16px; }
  .pl-stat-icon { width: 38px; height: 38px; }
  .pl-stat-value { font-size: 22px; }
}
@media (max-width: 640px) {
  .content { padding: 16px; }
  .pl-header-left h2 { font-size: 18px; }
  .pl-stats { grid-template-columns: 1fr; gap: 10px; }
  .pl-stat { flex-direction: row; align-items: center; gap: 14px; padding: 14px 16px; }
  .pl-stat-top { flex-direction: column; align-items: flex-start; gap: 8px; flex: 1; }
  .pl-stat-value { font-size: 20px; }
  .pl-table-card { padding: 16px; }
  .pl-search { padding: 14px; }
  .pl-search-form { flex-direction: column; align-items: stretch; }
  .pl-search-form .btn { width: 100%; }
}
</style>

<!-- Header -->
<div class="pl-header">
  <div class="pl-header-left">
    <h2>👥 Kelola Pelanggan</h2>
    <p>Total <b><?= number_format($total_all) ?></b> pelanggan · <b><?= number_format($aktif) ?></b> aktif</p>
  </div>
</div>

<!-- Stats -->
<div class="pl-stats">
  <div class="pl-stat cyan">
    <div class="pl-stat-top">
      <div class="pl-stat-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <span class="pl-stat-label">Total</span>
    </div>
    <div class="pl-stat-value"><?= number_format($total_all) ?></div>
    <div class="pl-stat-desc">Pelanggan terdaftar</div>
  </div>

  <div class="pl-stat green">
    <div class="pl-stat-top">
      <div class="pl-stat-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <span class="pl-stat-label">Aktif</span>
    </div>
    <div class="pl-stat-value"><?= number_format($aktif) ?></div>
    <div class="pl-stat-desc">Dapat login</div>
  </div>

  <div class="pl-stat red">
    <div class="pl-stat-top">
      <div class="pl-stat-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
      </div>
      <span class="pl-stat-label">Nonaktif</span>
    </div>
    <div class="pl-stat-value"><?= number_format($total_all - $aktif) ?></div>
    <div class="pl-stat-desc">Tidak dapat login</div>
  </div>
</div>

<!-- Search -->
<div class="pl-search">
  <form method="get" class="pl-search-form">
    <input type="text" name="q" class="pl-search-input" 
           placeholder="🔍 Cari nama, username, email, atau telepon..." 
           value="<?= e($search) ?>">
    <button type="submit" class="btn btn-primary">Cari</button>
    <?php if ($search): ?>
      <a href="<?= PATH_ADMIN ?>pelanggan.php" class="btn btn-secondary">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Table -->
<div class="pl-table-card">
  <div class="pl-table-header">
    <h3 class="pl-table-title">📋 Daftar Pelanggan</h3>
    <span class="pl-table-info">
      <?php if ($search): ?>
        Hasil: <b><?= number_format($total_data) ?></b>
      <?php else: ?>
        Total: <b><?= number_format($total_all) ?></b>
      <?php endif; ?>
    </span>
  </div>

  <?php if ($pelanggan->num_rows === 0): ?>
    <div class="pl-empty">
      <div class="pl-empty-icon"><?= $search ? '🔍' : '👥' ?></div>
      <div class="pl-empty-text">
        <?= $search ? 'Tidak ada hasil untuk "' . e($search) . '"' : 'Belum ada pelanggan terdaftar' ?>
      </div>
    </div>
  <?php else: ?>
    <div class="pl-table-scroll">
      <table>
        <thead>
          <tr>
            <th>Pelanggan</th>
            <th>Kontak</th>
            <th>Kendaraan</th>
            <th>Transaksi</th>
            <th>Total Bayar</th>
            <th>Terdaftar</th>
            <th>Status</th>
            <th style="text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($p = $pelanggan->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="pl-user">
                  <div class="pl-avatar <?= $p['aktif'] ? 'on' : 'off' ?>">
                    <?= strtoupper(substr($p['nama_lengkap'], 0, 1)) ?>
                  </div>
                  <div class="pl-user-info">
                    <div class="pl-user-name" title="<?= e($p['nama_lengkap']) ?>">
                      <?= e($p['nama_lengkap']) ?>
                    </div>
                    <div class="pl-user-sub">@<?= e($p['username']) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-size:12.5px;"><?= e($p['email'] ?: '-') ?></div>
                <div style="font-size:11px;color:#94a3b8;"><?= e($p['no_telepon'] ?: '-') ?></div>
              </td>
              <td><span class="badge badge-primary">🚗 <?= $p['total_kendaraan'] ?></span></td>
              <td><span class="badge badge-info">🅿️ <?= $p['total_transaksi'] ?></span></td>
              <td><b><?= rupiah($p['total_bayar']) ?></b></td>
              <td><div style="font-size:12.5px;"><?= date('d/m/Y', strtotime($p['created_at'])) ?></div></td>
              <td>
                <?php if ($p['aktif']): ?>
                  <span class="badge badge-success">Aktif</span>
                <?php else: ?>
                  <span class="badge badge-danger">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;">
                <form method="post" class="pl-actions">
                  <input type="hidden" name="id_user" value="<?= $p['id_user'] ?>">
                  <input type="hidden" name="redirect_page" value="<?= $pagi['page'] ?>">
                  <button class="pl-btn-icon toggle" name="aksi" value="toggle" 
                          title="<?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                    <?= $p['aktif'] ? '🚫' : '✅' ?>
                  </button>
                  <button class="pl-btn-icon reset" name="aksi" value="reset" 
                          title="Reset password" 
                          onclick="return confirm('Reset password ke pelanggan123?')">🔑</button>
                  <button class="pl-btn-icon del" name="aksi" value="hapus" 
                          title="Hapus" 
                          onclick="return confirm('Yakin hapus pelanggan ini?')">🗑️</button>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>