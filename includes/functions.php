<?php
// ============================================================
// FUNGSI BANTU - PT. PARKIR BOGOR
// ============================================================

if (!function_exists('rupiah')) {
    function rupiah($angka) { return 'Rp ' . number_format((float)$angka, 0, ',', '.'); }
}

if (!function_exists('e')) {
    function e($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('redirect')) {
    function redirect($url) { header("Location: $url"); exit; }
}

if (!function_exists('flash')) {
    function flash($key, $msg = null) {
        if ($msg === null) {
            $val = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $val;
        }
        $_SESSION['flash'][$key] = $msg;
    }
}

if (!function_exists('log_aktivitas')) {
    function log_aktivitas($koneksi, $user_id, $aksi, $ket = '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '-';
        $stmt = $koneksi->prepare("INSERT INTO log_aktivitas (user_id, aksi, keterangan, ip_address) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $user_id, $aksi, $ket, $ip);
        $stmt->execute();
    }
}

if (!function_exists('hitung_biaya')) {
    function hitung_biaya($koneksi, $jenis, $waktu_masuk, $waktu_keluar = null, $hilang = false) {
        $waktu_keluar = $waktu_keluar ?: date('Y-m-d H:i:s');
        $masuk = new DateTime($waktu_masuk);
        $keluar = new DateTime($waktu_keluar);
        $detik = max(0, $keluar->getTimestamp() - $masuk->getTimestamp());
        $jam = max(1, (int)ceil($detik / 3600));
        $stmt = $koneksi->prepare("SELECT * FROM tarif_parkir WHERE jenis_kendaraan=?");
        $stmt->bind_param('s', $jenis);
        $stmt->execute();
        $tarif = $stmt->get_result()->fetch_assoc();
        if (!$tarif) return ['jam'=> $jam,'biaya'=>0,'denda'=>0,'total'=>0,'tarif_per_jam'=>0];
        $biaya = max($jam * (float)$tarif['tarif_per_jam'], (float)$tarif['tarif_minimal']);
        $biaya = min($biaya, (float)$tarif['tarif_maksimal']);
        $denda = $hilang ? (float)$tarif['denda_hilang_tiket'] : 0;
        return ['jam'=> $jam,'tarif_per_jam'=> (float)$tarif['tarif_per_jam'],'biaya'=> $biaya,'denda'=> $denda,'total'=> $biaya+$denda];
    }
}

if (!function_exists('generate_kode_tiket')) {
    function generate_kode_tiket($koneksi) {
        do {
            $kode = 'TKT' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(2)), 0, 3));
            $stmt = $koneksi->prepare("SELECT 1 FROM transaksi_parkir WHERE kode_tiket=?");
            $stmt->bind_param('s', $kode);
            $stmt->execute();
        } while ($stmt->get_result()->num_rows > 0);
        return $kode;
    }
}

if (!function_exists('validasi_plat')) {
    function validasi_plat($plat) {
        $plat = strtoupper(preg_replace('/\s+/', ' ', trim($plat)));
        return preg_match('/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{0,3}$/', $plat) ? $plat : false;
    }
}

if (!function_exists('plat_sedang_parkir')) {
    function plat_sedang_parkir($koneksi, $plat) {
        $stmt = $koneksi->prepare("SELECT t.*, s.kode_slot FROM transaksi_parkir t JOIN slot_parkir s ON t.id_slot=s.id_slot WHERE t.plat_nomor=? AND t.waktu_keluar IS NULL AND t.status='aktif' LIMIT 1");
        $stmt->bind_param('s', $plat);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

if (!function_exists('cari_kendaraan_aktif')) {
    function cari_kendaraan_aktif($koneksi, $plat) {
        $stmt = $koneksi->prepare("SELECT t.*, s.kode_slot, s.zona FROM transaksi_parkir t JOIN slot_parkir s ON t.id_slot=s.id_slot WHERE t.plat_nomor=? AND t.waktu_keluar IS NULL AND t.status IN ('aktif', 'menunggu_approval') ORDER BY t.waktu_masuk DESC LIMIT 1");
        $stmt->bind_param('s', $plat);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

if (!function_exists('slot_tersedia')) {
    function slot_tersedia($koneksi, $jenis) {
        $stmt = $koneksi->prepare("SELECT COUNT(*) c FROM slot_parkir WHERE jenis_kendaraan=? AND status='kosong'");
        $stmt->bind_param('s', $jenis);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['c'];
    }
}

if (!function_exists('nav_active')) {
    function nav_active($file, $current) { return $file === $current ? 'active' : ''; }
}

if (!function_exists('deteksi_shift')) {
    function deteksi_shift() {
        $jam = (int)date('H');
        if ($jam >= 6 && $jam < 14) return 'pagi';
        if ($jam >= 14 && $jam < 22) return 'siang';
        return 'malam';
    }
}

if (!function_exists('validasi_email')) {
    function validasi_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
}

if (!function_exists('validasi_username')) {
    function validasi_username($username) { return preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username); }
}

if (!function_exists('validasi_password')) {
    function validasi_password($password) { return strlen($password) >= 6; }
}

if (!function_exists('get_user')) {
    function get_user($koneksi, $id) {
        $stmt = $koneksi->prepare("SELECT * FROM users WHERE id_user=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

// Retrieve the logged-in user's name (full name) from the users table.
// Returns empty string if not logged in or user not found.
if (!function_exists('user_name')) {
    function user_name() {
        global $koneksi;
        $uid = user_id();
        if (!$uid) return '';
        $user = get_user($koneksi, $uid);
        return $user['name'] ?? '';
    }
}

if (!function_exists('tgl_indo')) {
    function tgl_indo($tanggal) {
        if (!$tanggal) return '-';
        $bulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $pecah = explode('-', date('Y-m-d', strtotime($tanggal)));
        return (int)$pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
    }
}

if (!function_exists('label_role')) {
    function label_role($role) {
        $labels = ['admin'=> '👨‍💼 Admin', 'operator'=> '👨‍🔧 Operator', 'pelanggan'=> '👤 Pelanggan'];
        return $labels[$role] ?? $role;
    }
}

if (!function_exists('format_durasi')) {
    function format_durasi($waktu_masuk, $waktu_keluar = null) {
        $waktu_keluar = $waktu_keluar ?: date('Y-m-d H:i:s');
        $detik = max(0, strtotime($waktu_keluar) - strtotime($waktu_masuk));
        return floor($detik / 3600) . 'j ' . floor(($detik % 3600) / 60) . 'm';
    }
}

if (!function_exists('parse_input_angka')) {
    function parse_input_angka($input) {
        if ($input === null || $input === '') return 0;
        $clean = preg_replace('/[^0-9]/', '', (string)$input);
        return $clean === '' ? 0 : (int)$clean;
    }
}

if (!function_exists('paginate')) {
    function paginate($total_data, $per_page = 10, $page_param = 'page') {
        $total_data = (int)$total_data;
        $per_page = max(1, (int)$per_page);
        $total_pages = max(1, (int)ceil($total_data / $per_page));
        $page = isset($_GET[$page_param]) ? (int)$_GET[$page_param] : 1;
        $page = max(1, min($page, $total_pages));
        $offset = ($page - 1) * $per_page;
        return [
            'page'        => $page,
            'per_page'    => $per_page,
            'offset'      => $offset,
            'total_data'  => $total_data,
            'total_pages' => $total_pages,
            'from'        => $total_data > 0 ? $offset + 1 : 0,
            'to'          => min($offset + $per_page, $total_data),
            'param'       => $page_param,
            'always_show' => true,
        ];
    }
}

if (!function_exists('render_pagination')) {
    function render_pagination($pagi, $base_query = [], $anchor = '', $always_show = true) {
        if (!isset($pagi['total_data']) || $pagi['total_data'] <= 0) return '';
        $show_always = isset($pagi['always_show']) ? $pagi['always_show'] : $always_show;
        if ($pagi['total_pages'] <= 1 && !$show_always) return '';

        $param_name = $pagi['param'] ?? 'page';
        $query = $_GET;
        if (!empty($base_query) && is_array($base_query)) {
            $query = array_merge($query, $base_query);
        }
        unset($query[$param_name]);

        $build_url = function($page) use ($query, $param_name, $anchor) {
            $query[$param_name] = $page;
            $qs = http_build_query($query);
            return '?' . $qs . ($anchor ? $anchor : '');
        };

        $html = '<div class="pagination">';
        $html .= '<div class="pagination-info">Menampilkan <b>' . $pagi['from'] . '-' . $pagi['to'] . '</b> dari <b>' . number_format($pagi['total_data']) . '</b> data';
        if ($pagi['total_pages'] > 0) {
            $html .= ' <span style="opacity:0.65;font-size:11.5px;margin-left:4px;">(Halaman ' . $pagi['page'] . ' dari ' . $pagi['total_pages'] . ')</span>';
        }
        $html .= '</div>';
        $html .= '<div class="pagination-buttons">';

        if ($pagi['page'] > 1) {
            $html .= '<a href="' . $build_url($pagi['page'] - 1) . '" class="page-btn">‹ Prev</a>';
        } else {
            $html .= '<span class="page-btn disabled">‹ Prev</span>';
        }

        if ($pagi['total_pages'] <= 1) {
            $html .= '<span class="page-btn active">1</span>';
        } else {
            $start = max(1, $pagi['page'] - 2);
            $end = min($pagi['total_pages'], $pagi['page'] + 2);
            if ($start > 1) {
                $html .= '<a href="' . $build_url(1) . '" class="page-btn">1</a>';
                if ($start > 2) $html .= '<span class="page-btn disabled">...</span>';
            }
            for ($i = $start; $i <= $end; $i++) {
                if ($i === $pagi['page']) {
                    $html .= '<span class="page-btn active">' . $i . '</span>';
                } else {
                    $html .= '<a href="' . $build_url($i) . '" class="page-btn">' . $i . '</a>';
                }
            }
            if ($end < $pagi['total_pages']) {
                if ($end < $pagi['total_pages'] - 1) $html .= '<span class="page-btn disabled">...</span>';
                $html .= '<a href="' . $build_url($pagi['total_pages']) . '" class="page-btn">' . $pagi['total_pages'] . '</a>';
            }
        }

        if ($pagi['page'] < $pagi['total_pages']) {
            $html .= '<a href="' . $build_url($pagi['page'] + 1) . '" class="page-btn">Next ›</a>';
        } else {
            $html .= '<span class="page-btn disabled">Next ›</span>';
        }
        $html .= '</div></div>';
        return $html;
    }
}

if (!function_exists('render_qr_svg')) {
    /**
     * Render Kode QR murni offline ke dalam format SVG vector tajam
     * @param string $code Teks / kode tiket yang di-encode
     * @param int $size Dimensi lebar dan tinggi dalam pixel
     * @return string Elemen <svg> siap tampil
     */
    function render_qr_svg($code, $size = 150) {
        if (!class_exists('QRcode')) {
            require_once __DIR__ . '/qrcode.php';
        }
        try {
            $qr = new QRcode($code, 'M');
            $arr = $qr->getBarcodeArray();
            if (empty($arr) || empty($arr['bcode'])) {
                return '';
            }
            $rows = (int)$arr['num_rows'];
            $cols = (int)$arr['num_cols'];
            $margin = 2; // quiet zone 2 modul
            $total_w = $cols + ($margin * 2);
            $total_h = $rows + ($margin * 2);

            $path = '';
            for ($r = 0; $r < $rows; $r++) {
                for ($c = 0; $c < $cols; $c++) {
                    if ($arr['bcode'][$r][$c] == 1) {
                        $x = $c + $margin;
                        $y = $r + $margin;
                        $path .= "M{$x},{$y}h1v1h-1z ";
                    }
                }
            }

            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total_w . ' ' . $total_h . '" width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges" style="display:block;margin:0 auto;background:#ffffff;">';
            $svg .= '<rect width="' . $total_w . '" height="' . $total_h . '" fill="#ffffff"/>';
            $svg .= '<path d="' . trim($path) . '" fill="#000000"/>';
            $svg .= '</svg>';
            return $svg;
        } catch (Exception $e) {
            return '';
        }
    }
}
?>