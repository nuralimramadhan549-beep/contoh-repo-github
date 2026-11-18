<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background:#fef3c7;padding:20px;font-family:monospace;font-size:16px;'>";
    echo "<h2>POST Data Diterima:</h2>";
    echo "<pre>";
    var_dump($_POST);
    echo "</pre>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html>
<head><title>Test Form</title></head>
<body style="font-family:sans-serif;padding:20px;">
<h1>Test Form Sederhana</h1>
<form method="post">
    <label>Isi angka:</label><br>
    <input type="text" name="angka" style="padding:10px;font-size:16px;width:200px;">
    <br><br>
    <button type="submit" style="padding:10px 20px;font-size:16px;">Kirim</button>
</form>
</body>
</html>