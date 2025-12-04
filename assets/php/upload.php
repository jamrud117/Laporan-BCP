<?php
require __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

/* ============================================
   KONEKSI DATABASE
============================================ */
$koneksi = new mysqli("localhost", "root", "", "excel_data");
if ($koneksi->connect_error) die("Koneksi gagal: " . $koneksi->connect_error);

/* ============================================
   VALIDASI INPUT
============================================ */
if (!isset($_FILES['files']) || empty($_POST['tanggal_masuk'])) {
    echo "WARNING:⚠️ Harap pilih file dan isi tanggal masuk.";
    exit;
}

$tanggalMasuk = $_POST["tanggal_masuk"];
$files        = $_FILES["files"];


$allowedTypes = [
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
];

foreach ($_FILES['files']['type'] as $i => $type) {
    if (!in_array($type, $allowedTypes)) {
        echo "ERROR:❌ File " . $_FILES['files']['name'][$i] . " bukan file Excel!";
        exit;

    }
}


/* ============================================
   FUNGSI BANTUAN
============================================ */
function cell($sheet, $ref) {
    if (!$sheet) return "";
    try {
        $v = $sheet->getCell($ref)->getValue();
        return $v !== null ? trim($v) : "";
    } catch (Throwable) {
        return "";
    }
}

function findCol($header, $key) {
    $key = strtolower(trim($key));
    foreach ($header as $i => $h) {
        $h = strtolower(trim($h));
        if ($h === $key) return $i;
        if (strpos($h, $key) !== false) return $i;
    }
    return null;
}

function concatItemName($parts) {
    $final = [];

    foreach ($parts as $p) {
        $p = trim((string)$p);

        if ($p === "" || $p === "-" || strtolower($p) === "null" || strtolower($p) === "n/a") {
            continue;
        }

        $final[] = $p;
    }

    return implode(" ", $final);
}

/* ============================================
   PROSES SETIAP FILE EXCEL
============================================ */
foreach ($files["name"] as $i => $name) {

    if (!$name) continue;
    $tmp = $files["tmp_name"][$i];
    if (!file_exists($tmp)) continue;

    try {
        $ss = IOFactory::load($tmp);
    } catch (Throwable) {
        continue;
    }

    $HEADER  = $ss->getSheetByName("HEADER");
    $BARANG  = $ss->getSheetByName("BARANG");
    $DOKUMEN = $ss->getSheetByName("DOKUMEN");
    $ENTITAS = $ss->getSheetByName("ENTITAS");
    $KEMASAN = $ss->getSheetByName("KEMASAN");

    if (!$HEADER || !$BARANG) continue;

    /* ============================================
       HEADER DATA
    ============================================ */
    $nomorAju         = cell($HEADER, "A2");
    $nomorPendaftaran = cell($HEADER, "CP2");
    $tanggalDokumen   = cell($HEADER, "CF2");
    $kodeValuta       = cell($HEADER, "CI2");
    $ndpbm            = (float)cell($HEADER, "BW2");
    $fallbackBruto    = (float)cell($HEADER, "CB2");

    /* ============================================
       CEGAH DUPLIKASI DOKUMEN
    ============================================ */
    $cek = $koneksi->prepare("
        SELECT COUNT(*) FROM excel_data 
        WHERE nomor_aju = ?
    ");
    $cek->bind_param("s", $nomorAju);
    $cek->execute();
    $cek->bind_result($exists);
    $cek->fetch();
    $cek->close();

    if ($exists > 0) {
        echo "ERROR:⚠️ $nomorAju sudah ada!<br>";
        return;
    }

    /* ============================================
       DOKUMEN PELENGKAP
    ============================================ */
    $dokumenPelengkap = "";
    if ($DOKUMEN) {
        $arr = $DOKUMEN->toArray(null, true, true, true);
        $h   = array_map("strtolower", array_map("trim", $arr[1] ?? []));

        $ck = array_search("kode dokumen", $h);
        $cn = array_search("nomor dokumen", $h);

        if ($ck !== false && $cn !== false) {
            foreach (["380", "217", "640", "315"] as $prio) {
                foreach ($arr as $r => $row) {
                    if ($r == 1) continue;
                    if (trim($row[$ck] ?? "") === $prio) {
                        $dokumenPelengkap = trim($row[$cn] ?? "");
                        break 2;
                    }
                }
            }
        }
    }

    /* ============================================
       ENTITAS (CUSTOMER)
    ============================================ */
    $namaCustomer = "";
    if ($ENTITAS) {
        $arr = $ENTITAS->toArray(null, true, true, true);
        $h   = array_map("strtolower", array_map("trim", $arr[1] ?? []));

        $ck = array_search("kode entitas", $h);
        $cn = array_search("nama entitas", $h);

        if ($ck !== false && $cn !== false) {
            foreach ($arr as $r => $row) {
                if ($r == 1) continue;
                if (trim($row[$ck] ?? "") == "3") {
                    $namaCustomer = trim($row[$cn] ?? "");
                    break;
                }
            }
        }
    }

    /* ============================================
       KEMASAN
    ============================================ */
    $jumlahKemasan = "";
    $tipeKemasan   = "";
    if ($KEMASAN) {
        $arr = $KEMASAN->toArray(null, true, true, true);
        $h   = array_map("strtolower", array_map("trim", $arr[1] ?? []));

        $cj = array_search("jumlah kemasan", $h);
        $ct = array_search("kode kemasan", $h);

        if ($cj !== false && $ct !== false && isset($arr[2])) {
            $jumlahKemasan = (int)($arr[2][$cj] ?? 0);
            $tipeKemasan   = trim($arr[2][$ct] ?? "");
        }
    }

    /* ============================================
       BARANG (PER ITEM)
    ============================================ */
    $rows = $BARANG->toArray(null, true, true, true);
    $headerRow = array_map("strtolower", array_map("trim", $rows[1]));

    $cSeri   = findCol($headerRow, "seri");
    $cQty    = findCol($headerRow, "jumlah");
    $cSat    = findCol($headerRow, "satuan");
    $cCif    = findCol($headerRow, "cif");
    $cHarga  = findCol($headerRow, "harga");
    $cNetto  = findCol($headerRow, "netto");
    $cBruto  = findCol($headerRow, "bruto");
    $cKode   = findCol($headerRow, "kode");

    // Kolom untuk concat
    $cUraian = findCol($headerRow, "uraian");
    $cMerek  = findCol($headerRow, "merek");
    $cTipe   = findCol($headerRow, "tipe");
    $cUkuran = findCol($headerRow, "ukuran");
    $cSpek   = findCol($headerRow, "spesifikasi");

    $kodeTujuan = cell($HEADER, "N2");
    $map = [
        "1" => "PENYERAHAN BKP",
        "2" => "PENYERAHAN JKP",
        "3" => "RETUR",
        "4" => "NON PENYERAHAN",
        "5" => "LAINNYA"
    ];
    $tujuanPengiriman = $map[$kodeTujuan] ?? "TIDAK DIKETAHUI";

    /* ============================================
       PREPARE QUERY
    ============================================ */
    $stmt = $koneksi->prepare("
        INSERT INTO excel_data (
            tanggal_masuk, nomor_aju, nomor_pendaftaran, tanggal_dokumen,
            dokumen_pelengkap, nama_customer,
            jumlah_kemasan, tipe_kemasan,
            nomor_seri_barang, kode_barang, nama_item, quantity_item, satuan_barang,
            netto, bruto, valuta, cif, ndpbm, harga_penyerahan,
            kode_tujuan_pengiriman, tujuan_pengiriman,
            is_fallback_bruto
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    if (!$stmt) continue;

    /* ============================================
       LOOP ITEM BARANG
    ============================================ */
    for ($r = 2; $r <= count($rows); $r++) {
        if (!isset($rows[$r])) continue;

        $rw = $rows[$r];
        if ($cSeri === null || empty($rw[$cSeri])) continue;

        $seri     = trim($rw[$cSeri]);
        $qty      = (int)($rw[$cQty] ?? 0);
        $satuan   = trim($rw[$cSat] ?? "");
        $cif      = (float)($rw[$cCif] ?? 0);
        $netto    = (float)($rw[$cNetto] ?? 0);
        $kodeBrg  = trim($rw[$cKode] ?? "");

        /* ===== CONCAT NAMA ITEM ===== */
        $namaItem = concatItemName([
            $rw[$cUraian] ?? "",
            $rw[$cMerek]  ?? "",
            $rw[$cTipe]   ?? "",
            $rw[$cUkuran] ?? "",
            $rw[$cSpek]   ?? ""
        ]);

        if ($namaItem === "") {
            $namaItem = trim($rw[$cUraian] ?? "");
        }

        /* ===== HARGA ===== */
        $harga = (float)($rw[$cHarga] ?? 0);
        if ($harga <= 0) {
            $harga = $cif * $ndpbm;
        }

        /* ===== BRUTO (FALLBACK) ===== */
        $gwRaw = $rw[$cBruto] ?? "";

        if ($gwRaw === "" || $gwRaw === null || floatval($gwRaw) == 0) {
            $brutoItem = floatval($fallbackBruto); // fallback dari HEADER
            $isFallback = 1;
        } else {
            $brutoItem = floatval($gwRaw);
            $isFallback = 0;
        }


        /* ===== INSERT ===== */
        $stmt->bind_param(
            "ssssssissssisddsdidssi",
            $tanggalMasuk,
            $nomorAju,
            $nomorPendaftaran,
            $tanggalDokumen,
            $dokumenPelengkap,
            $namaCustomer,
            $jumlahKemasan,
            $tipeKemasan,
            $seri,
            $kodeBrg,
            $namaItem,
            $qty,
            $satuan,
            $netto,
            $brutoItem,
            $kodeValuta,
            $cif,
            $ndpbm,
            $harga,
            $kodeTujuan,
            $tujuanPengiriman,
            $isFallback
        );

        $stmt->execute();
    }

    $stmt->close();
}

$koneksi->close();
echo "SUCCESS: Upload & ekstraksi selesai!";
