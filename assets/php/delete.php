<?php
$koneksi = new mysqli("localhost","root","","excel_data");

if (isset($_GET['aju'])) {
    $aju = $koneksi->real_escape_string($_GET['aju']);
    $koneksi->query("DELETE FROM excel_data WHERE nomor_aju = '$aju'");
    echo "Nomor aju $aju berhasil dihapus.";
} else {
    echo "Nomor AJU tidak ditemukan.";
}
