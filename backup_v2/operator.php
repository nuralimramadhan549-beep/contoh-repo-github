<?php
require_once 'includes/auth.php';
cek_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tambah'])) {
        $nama = trim($_POST['nama_lengkap']);
        $user = trim($_POST['username']);
        $pass = $_POST['password'];
        $role = $_POST['role'];
        $shift = $_POST['shift'] ?? 'pagi';

        if (strlen($pass) < 6) {
            flash('error', 'Password minimal 6 karakter.');
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $koneksi->prepare("INSERT INTO users (nama_lengkap,username,password,role,shift) VALUES (?,?,?,?,?)");
            $stmt->bind_param('sssss', $nama, $user, $hash, $role, $shift);
            if ($stmt->execute()) {
                log_aktivitas($koneksi, user_id(), 'tambah_user', "User: $user");
                flash('success', "Operator $nama ditambahkan.");
            } else {
                flash('error', 'Gagal: ' . $koneksi->error);
            }
        }
    } elseif (isset($_POST['hapus'])) {
        $id = (int)$_POST['id_user'];
        if ($id === user_id()) {
            flash('error', 'Tidak bisa menghapus akun sendiri.');
        } else {
            $koneksi->query("DELETE FROM users WHERE id_user=$id AND username<>'admin'");
            flash('success', 'Operator dihapus.');
        }
    } elseif (isset($_POST['toggle'])) {
        $id = (int)$_POST['id_user'];
        $koneksi->query("UPDATE users SET aktif = 1 - aktif WHERE id_user=$id AND username<>'admin'");
        flash('success', 'Status diubah.');
    } elseif (isset($_POST['reset_pass'])) {
        $id = (int)$_POST['id_user'];
        $new = password_hash('password123', PASSWORD_DEFAULT);
        $koneksi->query("UPDATE users SET password='$new' WHERE id_user=$id");
        flash('success', 'Password direset ke: password123');
    }
    redirect('operator.php');
}

include 'includes/header.php';
?>
<h2>👥 Kelola Operator</h2>

<div class="card">
  <h3>➕ Tambah Pengguna Baru</h3>
  <form method="post">
    <div class="grid" style="grid-template-columns:1fr 1fr;">
      <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" required></div>
      <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
      <div class="form-group"><label>Password (min. 6)</label><input type="password" name="password" required minlength="6"></div>
      <div class="form-group"><label>Role</label>
        <select name="role"><option value="operator">Operator</option><option value="admin">Admin</option></select>
      </div>
      <div class="form-group"><label>Shift</label>
        <select name="shift"><option value="pagi">Pagi (06-14)</option><option value="siang">Siang (14-22)</option><option value="malam">Malam (22-06)</option></select>
      </div>
    </div>
    <button class="btn btn-primary" name="tambah" value="1">Tambah</button>
  </form>
</div>

<div class="card">
  <h3>📋 Daftar Pengguna</h3>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Shift</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php $q = $koneksi->query("SELECT * FROM users ORDER BY id_user"); while ($r = $q->fetch_assoc()): ?>
        <tr>
          <td><b><?= e($r['nama_lengkap']) ?></b></td>
          <td><code><?= e($r['username']) ?></code></td>
          <td><span class="badge <?= $r['role']==='admin'?'badge-warning':'badge-info' ?>"><?= e($r['role']) ?></span></td>
          <td><?= e($r['shift'] ?? '-') ?></td>
          <td>
            <?php if ($r['aktif']): ?>
              <span class="badge badge-success">Aktif</span>
            <?php else: ?>
              <span class="badge badge-danger">Nonaktif</span>
            <?php endif; ?>
          </td>
          <td><?= $r['last_login'] ? date('d/m/Y H:i', strtotime($r['last_login'])) : '-' ?></td>
          <td>
            <?php if ($r['username'] !== 'admin'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="id_user" value="<?= $r['id_user'] ?>">
              <button class="btn btn-secondary btn-sm" name="toggle" value="1">Toggle</button>
              <button class="btn btn-warning btn-sm" name="reset_pass" value="1" onclick="return confirm('Reset password ke password123?')">Reset</button>
              <button class="btn btn-danger btn-sm" name="hapus" value="1" onclick="return confirm('Hapus user ini?')">Hapus</button>
            </form>
            <?php else: ?>
              <span style="color:#64748b;font-size:12px;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'includes/footer.php'; ?>