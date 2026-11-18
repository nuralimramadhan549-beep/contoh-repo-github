<?php
// ============================================================
// DAFTAR PELANGGAN - OPERATOR (READ-ONLY)
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_operator();

$page_title = 'Daftar Pelanggan';
$page_subtitle = 'Pelanggan terdaftar di sistem';

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

$sql = "SELECT u.*,
    (SELECT COUNT(*) FROM kendaraan_pelanggan WHERE user_id = u.id_user) AS total_kendaraan,
    (SELECT COUNT(*) FROM transaksi_parkir WHERE user_id = u.id_user) AS total_transaksi
    FROM users u $where ORDER BY u.created_at DESC";

if (!empty($params)) {
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $pelanggan = $stmt->get_result();
} else {
    $pelanggan = $koneksi->query($sql);
}

// Total
$total = $koneksi->query("SELECT COUNT(*) c FROM users WHERE role='pelanggan'")->fetch_assoc()['c'];
$aktif_count = $koneksi->query("SELECT COUNT(*) c FROM users WHERE role='pelanggan' AND aktif=1")->fetch_assoc()['c'];

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2>👥 Daftar Pelanggan</h2>
    <p>Total <b><?= number_format($total) ?></b> pelanggan terdaftar · <b><?= number_format($aktif_count) ?></b> aktif</p>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card cyan">
    <div class="stat-icon">👥</div>
    <div class="stat-label">Total Pelanggan</div>
    <div class="stat-value"><?= number_format($total) ?></div>
    <div class="stat-desc">Terdaftar di sistem</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Aktif</div>
    <div class="stat-value"><?= number_format($aktif_count) ?></div>
    <div class="stat-desc">Dapat login</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon">🚫</div>
    <div class="stat-label">Nonaktif</div>
    <div class="stat-value"><?= number_format($total - $aktif_count) ?></div>
    <div class="stat-desc">Tidak dapat login</div>
  </div>
</div>

<!-- Search -->
<div class="card">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;">
    <input type="text" name="q" placeholder="Cari nama, username, email, telepon..." 
           value="<?= e($search) ?>"
           style="flex:1;min-width:250px;padding:11px 14px;border:1.5px solid #e2e8f0;border-radius:8px;">
    <button type="submit" class="btn btn-primary">🔍 Cari</button>
    <?php if ($search): ?>
      <a href="<?= PATH_OPERATOR ?>pelanggan.php" class="btn btn-secondary">Reset</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Nama</th>
          <th>Username</th>
          <th>Kontak</th>
          <th>Kendaraan</th>
          <th>Transaksi</th>
          <th>Terdaftar</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($pelanggan->num_rows === 0): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">
            <?= $search ? '🔍 Tidak ada hasil untuk "' . e($search) . '"' : '📭 Belum ada pelanggan terdaftar' ?>
          </td></tr>
        <?php else: while ($p = $pelanggan->fetch_assoc()): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#0891b2,#0e7490);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;">
                  <?= strtoupper(substr($p['nama_lengkap'], 0, 1)) ?>
                </div>
                <div>
                  <div style="font-weight:700;"><?= e($p['nama_lengkap']) ?></div>
                  <div style="font-size:11px;color:#94a3b8;">ID: <?= $p['id_user'] ?></div>
                </div>
              </div>
            </td>
            <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-size:12px;"><?= e($p['username']) ?></code></td>
            <td>
              <div style="font-size:12.5px;"><?= e($p['email'] ?: '-') ?></div>
              <div style="font-size:11px;color:#94a3b8;"><?= e($p['no_telepon'] ?: '-') ?></div>
            </td>
            <td><span class="badge badge-primary"><?= (int)$p['total_kendaraan'] ?> 🚗</span></td>
            <td><span class="badge badge-info"><?= (int)$p['total_transaksi'] ?> 🅿️</span></td>
            <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
            <td>
              <?php if ($p['aktif']): ?>
                <span class="badge badge-success">Aktif</span>
              <?php else: ?>
                <span class="badge badge-danger">Nonaktif</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info">
  <span class="alert-icon">ℹ️</span>
  <div>
    <b>Info:</b> Operator hanya bisa melihat daftar pelanggan. Untuk mengelola (aktif/nonaktif/hapus), hubungi admin.
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>