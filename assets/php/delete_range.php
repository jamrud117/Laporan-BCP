<?php
$koneksi = new mysqli("localhost", "root", "", "excel_data");

if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

$start = $_GET['start'] ?? "";
$end   = $_GET['end'] ?? "";

if (!$start || !$end) {
    die("Tanggal tidak lengkap.");
}

$sql = "
    DELETE FROM excel_data 
    WHERE tanggal_dokumen BETWEEN ? AND ?
";

$stmt = $koneksi->prepare($sql);

if (!$stmt) {
    die("SQL ERROR: " . $koneksi->error);
}

$stmt->bind_param("ss", $start, $end);
$stmt->execute();

echo "Berhasil menghapus data dokumen dari $start sampai $end";
?>
