<?php
$koneksi = new mysqli("localhost", "root", "", "excel_data");

if ($koneksi->connect_error) {
  die("Koneksi gagal: " . $koneksi->connect_error);
}

$aju = $_POST['nomor_aju'] ?? '';
$tgl = $_POST['tanggal_masuk'] ?? '';

if (!$aju || !$tgl) {
  exit("❌ Data tidak lengkap");
}

$stmt = $koneksi->prepare("
  UPDATE excel_data
  SET tanggal_masuk = ?
  WHERE nomor_aju = ?
");

$stmt->bind_param("ss", $tgl, $aju);

if ($stmt->execute()) {
  echo "✔️ Tanggal masuk berhasil diupdate";
} else {
  echo "❌ Gagal update data";
}

$stmt->close();
$koneksi->close();
