<?php
require_once __DIR__ . '/config/database.php';
echo "<pre style='font-family:monospace;font-size:14px;padding:20px;background:#f0fdf4;'>";
$users = [
    'admin'      => 'admin123',
    'operator1'  => 'operator123',
    'operator2'  => 'operator123',
    'pelanggan1' => 'pelanggan123'
];
foreach ($users as $username => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $koneksi->prepare("UPDATE users SET password=? WHERE username=?");
    $stmt->bind_param('ss', $hash, $username);
    if ($stmt->execute()) echo "✅ $username → $password (OK)\n";
}
echo "</pre>";
echo "<a href='login.php'>→ Login</a>";
?>