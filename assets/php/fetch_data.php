<?php
header("Content-Type: application/json; charset=UTF-8");

$koneksi = new mysqli("localhost", "root", "", "excel_data");

if ($koneksi->connect_error) {
    echo json_encode([]);
    exit;
}

$q = $koneksi->query("SELECT * FROM excel_data ORDER BY id ASC");

$raw = [];
while ($r = $q->fetch_assoc()) {
    $raw[] = $r;
}

/************************************
 * GROUP DATA PER NOMOR AJU
 ************************************/
$grouped = [];
foreach ($raw as $row) {
    $aju = $row["nomor_aju"];
    if (!isset($grouped[$aju])) $grouped[$aju] = [];
    $grouped[$aju][] = $row;
}

/************************************
 * APPLY FALLBACK BRUTO (GW)
 ************************************/
$final = [];

foreach ($grouped as $aju => $rows) {

    // Cari BRUTO HEADER
    $header_bruto = null;

    foreach ($rows as $r) {
        if (!empty($r["bruto"]) && $r["nomor_seri_barang"] == 0) {
            // Data header biasanya seri 0 atau tanpa seri
            $header_bruto = $r["bruto"];
            break;
        }
    }

    // Jika HEADER tidak ketemu, ambil dari row pertama saja
    if ($header_bruto === null) {
        $header_bruto = $rows[0]["bruto"];
    }

    // Apply ke setiap BARANG
    foreach ($rows as $r) {

        $bruto_final = $r["bruto"];

        // Jika bruto kosong, fallback ke header
        if (empty($r["bruto"]) || $r["bruto"] == 0) {
            $bruto_final = $header_bruto;
        }

        // Simpan hasil final
        $r["bruto"] = $bruto_final;

        $final[] = $r;
    }
}

echo json_encode($final);
