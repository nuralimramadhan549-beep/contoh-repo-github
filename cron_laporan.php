<?php
// ============================================================
// CRON LAPORAN HARIAN - PT. PARKIR BOGOR
// Akses: /cron_laporan.php?key=parkirbogor
// ============================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$key = $_GET['key'] ?? '';
$allowed = ['parkirbogor', 'cron_secret_2026'];
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && !in_array($key, $allowed)) {
    die('Access denied. Add ?key=parkirbogor');
}

if (!$is_cli) {
    echo '<!DOCTYPE html><html><head><title>Cron Laporan</title>';
    echo '<style>body{font-family:sans-serif;padding:30px;background:#f1f5f9;}';
    echo '.card{background:#fff;padding:24px;border-radius:12px;max-width:600px;margin:auto;box-shadow:0 4px 20px rgba(0,0,0,.1);}';
    echo 'h1{color:#1e3a8a;margin-bottom:16px;}';
    echo '.log{background:#f8fafc;padding:14px;border-radius:8px;font-family:monospace;font-size:13px;line-height:1.8;}';
    echo '.ok{color:#16a34a;font-weight:700;} .err{color:#dc2626;font-weight:700;}';
    echo '.btn{display:inline-block;padding:10px 20px;background:#1e3a8a;color:#fff;text-decoration:none;border-radius:8px;margin-top:16px;font-weight:600;}';
    echo '</style></head><body><div class="card">';
    echo '<h1>📊 Cron Laporan Harian</h1><div class="log">';
}

$hari = date('Y-m-d');
$kemarin = date('Y-m-d', strtotime('-1 day'));

foreach ([$hari, $kemarin] as $tgl) {
    $stmt = $koneksi->prepare("SELECT 
        COUNT(*) AS jml,
        COALESCE(SUM(CASE WHEN t.jenis_kendaraan='motor' THEN 1 ELSE 0 END), 0) AS motor,
        COALESCE(SUM(CASE WHEN t.jenis_kendaraan='mobil' THEN 1 ELSE 0 END), 0) AS mobil,
        COALESCE(SUM(p.total_biaya + p.denda), 0) AS total,
        COALESCE(SUM(CASE WHEN p.metode_pembayaran='tunai' THEN p.total_biaya + p.denda ELSE 0 END), 0) AS tunai,
        COALESCE(SUM(CASE WHEN p.metode_pembayaran<>'tunai' THEN p.total_biaya + p.denda ELSE 0 END), 0) AS qris
        FROM pembayaran p
        LEFT JOIN transaksi_parkir t ON p.id_transaksi = t.id_transaksi
        WHERE DATE(p.waktu_bayar) = ?");
    $stmt->bind_param('s', $tgl);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();

    // Cast ke int/float biar tidak NULL
    $jml = (int)($r['jml'] ?? 0);
    $motor = (int)($r['motor'] ?? 0);
    $mobil = (int)($r['mobil'] ?? 0);
    $total = (float)($r['total'] ?? 0);
    $tunai = (float)($r['tunai'] ?? 0);
    $qris = (float)($r['qris'] ?? 0);

    // INSERT / REPLACE
    $stmt = $koneksi->prepare("REPLACE INTO laporan_harian 
        (tanggal, jumlah_transaksi, jumlah_motor, jumlah_mobil, total_pendapatan, total_tunai, total_qris, slot_terpakai_max, diperbarui) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param('siiiidd',
        $tgl,
        $jml,
        $motor,
        $mobil,
        $total,
        $tunai,
        $qris
    );

    if ($stmt->execute()) {
        $msg = "[OK] Laporan $tgl: $jml transaksi · Motor: $motor · Mobil: $mobil · Total: " . rupiah($total);
        echo $is_cli ? "$msg\n" : '<span class="ok">✅ ' . $msg . '</span><br>';
    } else {
        $msg = "[ERROR] $tgl: " . $koneksi->error;
        echo $is_cli ? "$msg\n" : '<span class="err">❌ ' . $msg . '</span><br>';
    }
}

if (!$is_cli) {
    echo '</div>';
    echo '<a href="' . BASE_URL . 'admin/laporan.php" class="btn">→ Lihat Laporan</a>';
    echo '</div></body></html>';
}
?>