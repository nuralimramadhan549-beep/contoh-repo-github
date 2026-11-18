<?php
// ============================================================
// PROSES KELUAR - OPERATOR (FINAL FIX v4)
// Struktur form terpisah, tidak ada name conflict
// ============================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

cek_operator();

// Safety: definisi fungsi kalau belum ada
if (!function_exists('parse_input_angka')) {
    function parse_input_angka($input) {
        if ($input === null || $input === '') return 0;
        $clean = preg_replace('/[^0-9]/', '', (string)$input);
        return $clean === '' ? 0 : (int)$clean;
    }
}

$page_title = 'Proses Keluar';
$page_subtitle = 'Verifikasi & proses pembayaran kendaraan keluar';

$errors = [];
$cari = null;
$biaya_info = null;
$uid = user_id();
$pre_plat = $_GET['plat'] ?? '';

// ============================================================
// STEP 1: CARI KENDARAAN
// Trigger: tombol "Cari" di form search
// ============================================================
if (isset($_POST['cari_kendaraan'])) {
    $plat = validasi_plat($_POST['plat_cari'] ?? '');
    
    if (!$plat) {
        $errors[] = 'Format plat nomor tidak valid.';
    } else {
        $cari = cari_kendaraan_aktif($koneksi, $plat);
        if (!$cari) {
            $errors[] = "Kendaraan <b>" . e($plat) . "</b> tidak ditemukan atau sudah keluar.";
        } else {
            $biaya_info = hitung_biaya($koneksi, $cari['jenis_kendaraan'], $cari['waktu_masuk']);
        }
    }
}
// Cari dari URL (?plat=xxx)
elseif ($pre_plat) {
    $plat = validasi_plat($pre_plat);
    if ($plat) {
        $cari = cari_kendaraan_aktif($koneksi, $plat);
        if ($cari) {
            $biaya_info = hitung_biaya($koneksi, $cari['jenis_kendaraan'], $cari['waktu_masuk']);
        } else {
            $errors[] = "Kendaraan <b>" . e($plat) . "</b> tidak ditemukan.";
        }
    }
}

// ============================================================
// STEP 2: PROSES BAYAR
// Trigger: tombol "Proses Bayar" di form bayar
// ============================================================
if (isset($_POST['proses_bayar'])) {
    $id_trx = (int)($_POST['id_transaksi'] ?? 0);
    $hilang = !empty($_POST['hilang_tiket']);
    $metode = $_POST['metode'] ?? 'tunai';
    $bayar = parse_input_angka($_POST['nominal'] ?? '0');
    
    // Ambil transaksi
    $stmt = $koneksi->prepare("SELECT t.*, s.kode_slot FROM transaksi_parkir t 
        JOIN slot_parkir s ON t.id_slot=s.id_slot 
        WHERE t.id_transaksi=? AND t.waktu_keluar IS NULL LIMIT 1");
    $stmt->bind_param('i', $id_trx);
    $stmt->execute();
    $trx = $stmt->get_result()->fetch_assoc();

    if (!$trx) {
        $errors[] = 'Transaksi tidak ditemukan atau sudah selesai.';
    } else {
        $hitung = hitung_biaya($koneksi, $trx['jenis_kendaraan'], $trx['waktu_masuk'], null, $hilang);
        $total = $hitung['total'];

        // Validasi
        if ($metode === 'tunai') {
            if ($bayar <= 0) {
                $errors[] = "Masukkan nominal uang bayar terlebih dahulu.";
                // Set $cari supaya form tetap muncul dengan error
                $cari = $trx;
                $biaya_info = $hitung;
            } elseif ($bayar < $total) {
                $errors[] = "Uang bayar <b>" . rupiah($bayar) . "</b> kurang dari total <b>" . rupiah($total) . "</b>. Kurang <b>" . rupiah($total - $bayar) . "</b>.";
                $cari = $trx;
                $biaya_info = $hitung;
            }
        } else {
            $bayar = $total;
        }

        if (empty($errors)) {
            $koneksi->begin_transaction();
            try {
                $stmt = $koneksi->prepare("UPDATE transaksi_parkir 
                    SET waktu_keluar=NOW(), durasi_jam=?, status='selesai',
                        disetujui_oleh=?, waktu_persetujuan=NOW()
                    WHERE id_transaksi=?");
                $stmt->bind_param('iii', $hitung['jam'], $uid, $id_trx);
                $stmt->execute();

                $koneksi->query("UPDATE slot_parkir SET status='kosong' WHERE id_slot={$trx['id_slot']}");

                $kembalian = $metode === 'tunai' ? max(0, $bayar - $total) : 0;

                $stmt = $koneksi->prepare("INSERT INTO pembayaran 
                    (id_transaksi, total_biaya, denda, metode_pembayaran, bayar, kembalian, kasir_id) 
                    VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('iddsddi', $id_trx, $hitung['biaya'], $hitung['denda'], $metode, $bayar, $kembalian, $uid);
                $stmt->execute();

                log_aktivitas($koneksi, $uid, 'operator_proses_keluar', 
                    "Plat: {$trx['plat_nomor']}, Total: $total, Bayar: $bayar");
                $koneksi->commit();

                flash('success', "Pembayaran berhasil! Total: <b>" . rupiah($total) . "</b> · Kembalian: <b>" . rupiah($kembalian) . "</b>");
                redirect(PATH_OPERATOR . 'struk.php?id=' . $id_trx);
            } catch (Exception $ex) {
                $koneksi->rollback();
                $errors[] = 'Gagal: ' . $ex->getMessage();
                $cari = $trx;
                $biaya_info = $hitung;
            }
        }
    }
}

// ============================================================
// DATA UNTUK TAMPILAN
// ============================================================
$aktif = $koneksi->query("SELECT * FROM v_transaksi_aktif ORDER BY 
    CASE WHEN status='menunggu_approval' THEN 0 ELSE 1 END,
    waktu_masuk ASC");

$waiting = (int)$koneksi->query("SELECT COUNT(*) c FROM transaksi_parkir 
    WHERE status='menunggu_approval'")->fetch_assoc()['c'];

include __DIR__ . '/../includes/header.php';
?>

<style>
.info-alert {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.3);
  border-left: 4px solid #f59e0b;
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 20px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
}
.info-alert-icon { font-size: 24px; flex-shrink: 0; }
.info-alert-text { font-size: 13.5px; color: #fde68a; line-height: 1.6; }
.info-alert-text b { color: #fbbf24; }

.vehicle-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  background: #131c2e;
  border: 1px solid #1e293b;
  border-radius: 12px;
  gap: 14px;
  flex-wrap: wrap;
  transition: all 0.2s ease;
}
.vehicle-row:hover { background: #182338; border-color: #334155; }
.vehicle-row.waiting {
  background: rgba(245, 158, 11, 0.12);
  border-color: rgba(245, 158, 11, 0.4);
  border-left: 4px solid #f59e0b;
}

.pay-summary {
  background: rgba(14, 165, 233, 0.1);
  padding: 18px;
  border-radius: 12px;
  margin-bottom: 16px;
  border: 1px solid rgba(14, 165, 233, 0.3);
}
.pay-summary-label {
  font-size: 11px;
  font-weight: 800;
  color: #38bdf8;
  text-transform: uppercase;
  letter-spacing: .8px;
  margin-bottom: 4px;
}
.pay-summary-value {
  font-size: 28px;
  font-weight: 800;
  color: #38bdf8;
  line-height: 1;
  letter-spacing: -.5px;
}

.pay-input-wrap { position: relative; }
.pay-input-wrap input {
  font-size: 18px;
  font-weight: 800;
  padding: 14px 16px 14px 50px;
  letter-spacing: 1px;
}
.pay-input-prefix {
  position: absolute;
  left: 16px;
  top: 50%;
  transform: translateY(-50%);
  font-weight: 800;
  font-size: 16px;
  color: #64748b;
  pointer-events: none;
}
.quick-pay {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 8px;
}
.quick-pay button {
  padding: 8px 14px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  font-family: inherit;
}
.quick-pay button:hover { background: #3b82f6; color: #fff; border-color: #3b82f6; }

.kembalian-box {
  margin-top: 10px;
  padding: 12px 16px;
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.kembalian-box.error {
  background: #fef2f2;
  border-color: #fca5a5;
}
.kembalian-label {
  font-size: 12px;
  font-weight: 700;
  color: #166534;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.kembalian-box.error .kembalian-label { color: #991b1b; }
.kembalian-value {
  font-size: 18px;
  font-weight: 800;
  color: #16a34a;
}
.kembalian-box.error .kembalian-value { color: #dc2626; }

.help-text {
  font-size: 12px;
  color: #64748b;
  margin-top: 6px;
}
</style>

<h2 style="margin-bottom:6px;">🚗 Proses Keluar</h2>
<p style="color:#64748b;margin-bottom:20px;">Verifikasi pembayaran dan lepaskan kendaraan dari area parkir.</p>

<?php if ($waiting > 0): ?>
  <div class="info-alert">
    <div class="info-alert-icon">⏳</div>
    <div class="info-alert-text">
      <b><?= $waiting ?> pelanggan</b> menunggu persetujuan keluar.
    </div>
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <span class="alert-icon">❌</span>
    <div><?php foreach ($errors as $er): ?><div>• <?= $er ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<!-- ============================================================
     FORM 1: CARI KENDARAAN
     ============================================================ -->
<div class="card">
  <div class="card-header"><h3>🔍 Cari Kendaraan</h3></div>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;" autocomplete="off">
    <input type="text" name="plat_cari" required placeholder="Masukkan plat nomor..."
           value="<?= e($_POST['plat_cari'] ?? $pre_plat) ?>"
           style="flex:1;min-width:220px;padding:12px;border:1.5px solid #e2e8f0;border-radius:8px;text-transform:uppercase;font-weight:700;font-size:16px;letter-spacing:2px;"
           oninput="this.value=this.value.toUpperCase()">
    <button type="submit" name="cari_kendaraan" value="1" class="btn btn-warning">🔍 Cari</button>
  </form>
</div>

<?php if ($cari && $biaya_info): ?>
  <!-- ============================================================
       FORM 2: BAYAR (TERPISAH TOTAL DARI FORM CARI)
       ============================================================ -->
  <div class="card" style="border-left:5px solid #f59e0b;">
    <div class="card-header">
      <h3>💳 Konfirmasi Pembayaran</h3>
      <?php if ($cari['status'] === 'menunggu_approval'): ?>
        <span class="badge badge-warning" style="padding:6px 14px;">⏳ Menunggu Persetujuan</span>
      <?php endif; ?>
    </div>
    
    <div class="table-wrapper mb-3">
      <table>
        <tr><td><b>Kode Tiket</b></td><td><?= e($cari['kode_tiket']) ?></td></tr>
        <tr><td><b>Plat Nomor</b></td><td><b style="font-size:18px;letter-spacing:1px;"><?= e($cari['plat_nomor']) ?></b></td></tr>
        <tr><td><b>Jenis</b></td><td><?= $cari['jenis_kendaraan'] === 'motor' ? '🏍️ Motor' : '🚗 Mobil' ?></td></tr>
        <tr><td><b>Slot</b></td><td><?= e($cari['kode_slot']) ?></td></tr>
        <tr><td><b>Waktu Masuk</b></td><td><?= date('d/m/Y H:i:s', strtotime($cari['waktu_masuk'])) ?></td></tr>
        <tr><td><b>Durasi</b></td><td><b><?= $biaya_info['jam'] ?> jam</b></td></tr>
        <tr><td><b>Tarif / Jam</b></td><td><?= rupiah($biaya_info['tarif_per_jam']) ?></td></tr>
        <tr style="background:#fef3c7;"><td><b>Biaya Parkir</b></td><td><b style="font-size:18px;color:#92400e;"><?= rupiah($biaya_info['biaya']) ?></b></td></tr>
      </table>
    </div>

    <form method="post" onsubmit="return validasiBayar()" id="formBayar" autocomplete="off">
      <!-- Data transaksi -->
      <input type="hidden" name="id_transaksi" value="<?= $cari['id_transaksi'] ?>">

      <!-- Checkbox hilang tiket -->
      <div class="form-group">
        <label style="cursor:pointer;display:flex;align-items:center;gap:8px;font-weight:600;">
          <input type="checkbox" name="hilang_tiket" id="hilang" value="1" 
                 onchange="updateTotal()" style="width:auto;cursor:pointer;">
          🎫 Tiket hilang (denda <?= rupiah(10000) ?>)
        </label>
      </div>

      <!-- Metode pembayaran -->
      <div class="form-group">
        <label>💳 Metode Pembayaran</label>
        <select name="metode" id="metode" onchange="toggleBayar()" required>
          <option value="tunai">💵 Tunai</option>
          <option value="qris">📱 QRIS</option>
          <option value="e-wallet">📲 E-Wallet</option>
        </select>
      </div>

      <!-- Input uang bayar -->
      <div id="grup-bayar">
        <div class="form-group">
          <label>💵 Uang Bayar</label>
          <div class="pay-input-wrap">
            <span class="pay-input-prefix">Rp</span>
            <input type="text" 
                   name="nominal"
                   id="nominal" 
                   value="" 
                   oninput="onInputChange(this)"
                   placeholder="Contoh: 10000"
                   inputmode="numeric"
                   autocomplete="off"
                   spellcheck="false">
          </div>
          <div class="help-text">💡 Ketik angka saja tanpa titik. Contoh: <code>10000</code></div>
          <div class="quick-pay">
            <button type="button" onclick="setNominal(2000)">Rp 2.000</button>
            <button type="button" onclick="setNominal(5000)">Rp 5.000</button>
            <button type="button" onclick="setNominal(10000)">Rp 10.000</button>
            <button type="button" onclick="setNominal(20000)">Rp 20.000</button>
            <button type="button" onclick="setNominal(50000)">Rp 50.000</button>
            <button type="button" onclick="setNominal(100000)">Rp 100.000</button>
          </div>
          
          <div class="kembalian-box" id="kembalian-box">
            <span class="kembalian-label">💵 Kembalian</span>
            <span class="kembalian-value" id="kembalian">Rp 0</span>
          </div>
        </div>
      </div>

      <!-- Total -->
      <div class="pay-summary">
        <div class="pay-summary-label">💰 Total Bayar</div>
        <div class="pay-summary-value" id="total"><?= rupiah($biaya_info['total']) ?></div>
      </div>

      <!-- Tombol submit -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" name="proses_bayar" value="1" class="btn btn-success btn-lg">
          ✅ Proses Bayar & Keluarkan
        </button>
        <a href="<?= PATH_OPERATOR ?>keluar.php" class="btn btn-secondary btn-lg">Batal</a>
      </div>
    </form>
  </div>

  <script>
  // Konfigurasi
  const BIAYA = <?= (float)$biaya_info['biaya'] ?>;
  const DENDA_HILANG = 10000;

  // Parse angka dari input user
  function parseAngka(str) {
    if (!str) return 0;
    const clean = String(str).replace(/[^0-9]/g, '');
    return clean ? parseInt(clean, 10) : 0;
  }

  // Hitung total
  function hitungTotal() {
    const hilang = document.getElementById('hilang').checked;
    return BIAYA + (hilang ? DENDA_HILANG : 0);
  }

  // Saat user ketik di input
  function onInputChange(el) {
    const angka = parseAngka(el.value);
    // Biarkan apa yang user ketik, tapi kalau bukan angka murni, bersihkan
    if (angka > 0) {
      el.value = angka;
    } else if (el.value !== '') {
      el.value = '';
    }
    updateKembalian(angka);
  }

  // Set nominal dari tombol cepat
  function setNominal(nominal) {
    document.getElementById('nominal').value = nominal;
    updateKembalian(nominal);
  }

  // Update kembalian
  function updateKembalian(bayar) {
    const total = hitungTotal();
    const kembali = bayar - total;
    const box = document.getElementById('kembalian-box');
    const el = document.getElementById('kembalian');
    
    if (bayar === 0) {
      box.classList.remove('error');
      el.textContent = 'Rp 0';
      el.style.color = '#16a34a';
    } else if (kembali < 0) {
      box.classList.add('error');
      el.textContent = 'Kurang Rp ' + Math.abs(kembali).toLocaleString('id-ID');
      el.style.color = '#dc2626';
    } else {
      box.classList.remove('error');
      el.textContent = 'Rp ' + kembali.toLocaleString('id-ID');
      el.style.color = '#16a34a';
    }
  }

  // Update total
  function updateTotal() {
    const total = hitungTotal();
    document.getElementById('total').textContent = 'Rp ' + total.toLocaleString('id-ID');
    const bayar = parseAngka(document.getElementById('nominal').value);
    updateKembalian(bayar);
  }

  // Toggle bayar (non-tunai auto-fill)
  function toggleBayar() {
    const m = document.getElementById('metode').value;
    if (m !== 'tunai') {
      const total = hitungTotal();
      document.getElementById('nominal').value = total;
      updateKembalian(total);
    }
  }

  // Validasi sebelum submit
  function validasiBayar() {
    const metode = document.getElementById('metode').value;
    const total = hitungTotal();
    const bayar = parseAngka(document.getElementById('nominal').value);
    
    if (metode === 'tunai') {
      if (bayar <= 0) {
        alert('❌ Masukkan nominal uang bayar terlebih dahulu.');
        document.getElementById('nominal').focus();
        return false;
      }
      if (bayar < total) {
        alert('❌ Uang bayar Rp ' + bayar.toLocaleString('id-ID') + 
              ' KURANG dari total Rp ' + total.toLocaleString('id-ID') + 
              '\n\nKurang: Rp ' + (total - bayar).toLocaleString('id-ID'));
        document.getElementById('nominal').focus();
        return false;
      }
    }
    return true;
  }

  // Init
  document.addEventListener('DOMContentLoaded', updateTotal);
  </script>
<?php endif; ?>

<!-- ============================================================
     DAFTAR KENDARAAN AKTIF
     ============================================================ -->
<div class="card">
  <div class="card-header">
    <h3>🚙 Kendaraan Masih Parkir (<?= $aktif->num_rows ?>)</h3>
  </div>
  <div style="display:grid;gap:10px;">
    <?php if ($aktif->num_rows === 0): ?>
      <div style="text-align:center;padding:40px;color:#94a3b8;">
        🅿️ Tidak ada kendaraan aktif
      </div>
    <?php else: while ($r = $aktif->fetch_assoc()): 
      $is_waiting = $r['status'] === 'menunggu_approval';
    ?>
      <div class="vehicle-row <?= $is_waiting ? 'waiting' : '' ?>">
        <div style="flex:1;min-width:180px;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <b style="font-size:16px;letter-spacing:1px;"><?= e($r['plat_nomor']) ?></b>
            <?php if ($is_waiting): ?>
              <span class="badge badge-warning" style="font-size:10px;">⏳ MENUNGGU</span>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:#64748b;margin-top:4px;">
            <?= $r['jenis_kendaraan'] === 'motor' ? '🏍️' : '🚗' ?>
            Slot <?= e($r['kode_slot']) ?>
            · Masuk <?= date('H:i', strtotime($r['waktu_masuk'])) ?>
            · <?= format_durasi($r['waktu_masuk']) ?>
          </div>
          <?php if ($r['pelanggan_nama']): ?>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
              👤 <?= e($r['pelanggan_nama']) ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="flex-shrink:0;">
          <a href="<?= PATH_OPERATOR ?>keluar.php?plat=<?= urlencode($r['plat_nomor']) ?>" 
             class="btn btn-sm <?= $is_waiting ? 'btn-warning' : 'btn-secondary' ?>">
            <?= $is_waiting ? '💳 Proses Sekarang' : '💳 Bayar' ?>
          </a>
        </div>
      </div>
    <?php endwhile; endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>