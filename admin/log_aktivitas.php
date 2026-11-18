<?php
// ============================================================
// LOG AKTIVITAS - ADMIN
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_admin();

$page_title = 'Log Aktivitas';
$page_subtitle = 'Audit trail semua aktivitas pengguna';

$filter_user = (int)($_GET['user'] ?? 0);
$filter_aksi = trim($_GET['aksi'] ?? '');
$search = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
$params = [];
$types = '';

if ($filter_user > 0) {
    $where .= " AND l.user_id=?";
    $params[] = $filter_user;
    $types .= 'i';
}

if ($filter_aksi !== '') {
    $where .= " AND l.aksi=?";
    $params[] = $filter_aksi;
    $types .= 's';
}

if ($search !== '') {
    $where .= " AND (l.keterangan LIKE ? OR l.ip_address LIKE ?)";
    $s = "%$search%";
    $params[] = $s;
    $params[] = $s;
    $types .= 'ss';
}

$sql = "SELECT l.*, u.nama_lengkap, u.username, u.role
    FROM log_aktivitas l
    LEFT JOIN users u ON l.user_id = u.id_user
    $where
    ORDER BY l.waktu DESC LIMIT 200";

if (!empty($params)) {
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $koneksi->query($sql);
}

// Daftar user untuk filter
$users = $koneksi->query("SELECT id_user, nama_lengkap, username FROM users ORDER BY nama_lengkap");
// Daftar aksi unik
$aksi_list = $koneksi->query("SELECT DISTINCT aksi FROM log_aktivitas ORDER BY aksi");

$total = $koneksi->query("SELECT COUNT(*) c FROM log_aktivitas")->fetch_assoc()['c'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2>📝 Log Aktivitas</h2>
    <p>Total <b><?= number_format($total) ?></b> aktivitas tercatat (max 200 ditampilkan).</p>
  </div>
</div>

<!-- Filter -->
<div class="card">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <div class="form-group" style="flex:1;min-width:200px;margin:0;">
      <label>🔍 Cari</label>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Keterangan / IP...">
    </div>
    <div class="form-group" style="margin:0;">
      <label>👤 User</label>
      <select name="user">
        <option value="0">Semua User</option>
        <?php while ($u = $users->fetch_assoc()): ?>
          <option value="<?= $u['id_user'] ?>" <?= $filter_user == $u['id_user'] ? 'selected' : '' ?>>
            <?= e($u['nama_lengkap']) ?> (<?= e($u['username']) ?>)
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label>⚡ Aksi</label>
      <select name="aksi">
        <option value="">Semua Aksi</option>
        <?php while ($a = $aksi_list->fetch_assoc()): ?>
          <option value="<?= e($a['aksi']) ?>" <?= $filter_aksi === $a['aksi'] ? 'selected' : '' ?>>
            <?= e($a['aksi']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ($search || $filter_user || $filter_aksi): ?>
      <a href="<?= PATH_ADMIN ?>log_aktivitas.php" class="btn btn-secondary">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Log -->
<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Waktu</th>
          <th>User</th>
          <th>Role</th>
          <th>Aksi</th>
          <th>Keterangan</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($logs->num_rows === 0): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">Tidak ada log</td></tr>
        <?php else: while ($l = $logs->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= date('d/m/Y', strtotime($l['waktu'])) ?></div>
              <div style="font-size:11px;color:#94a3b8;"><?= date('H:i:s', strtotime($l['waktu'])) ?></div>
            </td>
            <td>
              <?php if ($l['nama_lengkap']): ?>
                <b><?= e($l['nama_lengkap']) ?></b>
                <div style="font-size:11px;color:#94a3b8;">@<?= e($l['username']) ?></div>
              <?php else: ?>
                <span style="color:#94a3b8;">System</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($l['role']): ?>
                <span class="badge badge-<?= $l['role'] === 'admin' ? 'warning' : ($l['role'] === 'operator' ? 'info' : 'primary') ?>">
                  <?= e($l['role']) ?>
                </span>
              <?php endif; ?>
            </td>
            <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-size:11.5px;"><?= e($l['aksi']) ?></code></td>
            <td style="font-size:12.5px;"><?= e($l['keterangan'] ?: '-') ?></td>
            <td><code style="font-size:11px;color:#94a3b8;"><?= e($l['ip_address'] ?: '-') ?></code></td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>