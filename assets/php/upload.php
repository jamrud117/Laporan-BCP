<?php
require __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

/* ============================================
   INIT STATUS & MESSAGE
============================================ */
$messages   = [];
$warnings = [];
$errors = [];
$hasError   = false;
$hasSuccess = false;
$successCount = 0;

/* ============================================
   DB CONNECTION
============================================ */
$koneksi = new mysqli("localhost", "root", "", "excel_data");
if ($koneksi->connect_error) {
    die("ERROR:❌ Koneksi database gagal");
}

/* ============================================
   VALIDASI INPUT
============================================ */
if (!isset($_FILES['files']) || empty($_POST['tanggal_masuk'])) {
    echo "WARNING:⚠️ Harap pilih file dan isi tanggal masuk.";
    exit;
}

$tanggalMasuk = $_POST['tanggal_masuk'];
$files = $_FILES['files'];

/* ============================================
   VALIDASI FILE TYPE
============================================ */
$allowedTypes = [
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
];

foreach ($files['type'] as $i => $type) {
    if (!in_array($type, $allowedTypes)) {
        $messages[] = "ERROR:❌ File {$files['name'][$i]} bukan Excel";
        $hasError = true;
    }
}

if ($hasError) {
    echo implode("<br>", $messages);
    exit;
}

/* ============================================
   HELPER FUNCTIONS (UTUH)
============================================ */
function cell($sheet, $ref) {
    try {
        $v = $sheet->getCell($ref)->getValue();
        return $v !== null ? trim((string)$v) : "";
    } catch (Throwable) {
        return "";
    }
}

function findCol($header, $key) {
    $key = strtolower(trim($key));
    foreach ($header as $i => $h) {
        $h = strtolower(trim((string)$h));
        if ($h === $key || strpos($h, $key) !== false) return $i;
    }
    return null;
}

function concatItemName($parts) {
    $final = [];
    foreach ($parts as $p) {
        $p = trim((string)$p);
        if ($p === "" || $p === "-" || strtolower($p) === "null" || strtolower($p) === "n/a") continue;
        $final[] = $p;
    }
    return implode(" ", $final);
}

/* ============================================
   PROSES FILE
============================================ */
foreach ($files['name'] as $i => $name) {

    if (!$name || !file_exists($files['tmp_name'][$i])) continue;

    try {
        $ss = IOFactory::load($files['tmp_name'][$i]);
    } catch (Throwable) {
        $messages[] = "ERROR:❌ Gagal membaca file $name";
        $hasError = true;
        continue;
    }

    $HEADER  = $ss->getSheetByName("HEADER");
    $BARANG  = $ss->getSheetByName("BARANG");
    $DOKUMEN = $ss->getSheetByName("DOKUMEN");
    $ENTITAS = $ss->getSheetByName("ENTITAS");
    $KEMASAN = $ss->getSheetByName("KEMASAN");

    if (!$HEADER || !$BARANG) continue;

    /* ================= HEADER ================= */
    $nomorAju         = cell($HEADER, "A2");
    $nomorPendaftaran = cell($HEADER, "CP2");
    $tanggalDokumen   = cell($HEADER, "CF2");
    $kodeValuta       = cell($HEADER, "CI2");
    $ndpbm            = (float)cell($HEADER, "BW2");
    $fallbackBruto    = (float)cell($HEADER, "CB2");
    $kodeTujuanRaw = trim(cell($HEADER, "N2"));
    $kodeTujuan = preg_replace('/[^0-9]/', '', $kodeTujuanRaw);
    if ($kodeTujuan === "" || $kodeTujuan === "0") {
        $kodeTujuan = null;
    }
    $kodeDokumen = cell($HEADER, "B2");

    /* ================= DUPLIKASI AJU ================= */
    $cek = $koneksi->prepare("SELECT 1 FROM excel_data WHERE nomor_aju = ? LIMIT 1");
    $cek->bind_param("s", $nomorAju);
    $cek->execute();
    $cek->store_result();

    if ($cek->num_rows > 0) {
        $warnings[] = "⚠️ Duplikat $nomorAju";
        $cek->close();
        continue;
    }
    $cek->close();

    /* ================= DOKUMEN ================= */
    $dokumenPelengkap = "";
    if ($DOKUMEN) {
        $arr = $DOKUMEN->toArray(null, true, true, true);
        $h = array_map("strtolower", array_map("trim", $arr[1] ?? []));
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

    /* ================= ENTITAS ================= */
    $myCompany = false;

    if ($ENTITAS) {
        $rows = $ENTITAS->toArray(null, true, true, true);
        $header = array_map("strtolower", array_map("trim", $rows[1] ?? []));

        $cKode = array_search("kode entitas", $header);
        $cNama = array_search("nama entitas", $header);

        if ($cKode !== false && $cNama !== false) {
            foreach ($rows as $r => $row) {
                if ($r == 1) continue;

                $kode = trim((string)$row[$cKode]);
                $nama = strtoupper(trim((string)$row[$cNama]));

                if ($kode === "3" && str_contains($nama, "CHINLI PLASTIC MATERIALS INDONESIA")) {
                    $myCompany = true;
                    break;
                }
            }
        }
    }
    $arahDokumen = "Masuk";

    switch ($kodeDokumen) {
        case "27":
            if ($myCompany) {
                $arahDokumen = "Keluar";
            }
            break;

        case "40":
            $arahDokumen = "Masuk";
            break;

        case "41":
            $arahDokumen = "Keluar";
            break;

        case "23":
            if ($myCompany) {
                $arahDokumen = "Keluar";
            }
            break;

        default:
            $arahDokumen = "Masuk";
    }
    $jenisDokumen = $kodeDokumen . " " . $arahDokumen;

    $mapCustomerEntitas = [
        "27" => [
            "Masuk"  => "7",
            "Keluar" => "8",
        ],
        "40" => [
            "Masuk"  => "9",
        ],
        "41" => [
            "Keluar" => "8",
        ],
        "23" => [
            "Masuk"  => "3",
            "Keluar" => "7",
        ],
    ];

    if (isset($mapCustomerEntitas[$kodeDokumen][$arahDokumen])) {
        $kodeCustomer = $mapCustomerEntitas[$kodeDokumen][$arahDokumen];
    }





    $namaCustomer = "";

    if ($ENTITAS) {
        foreach ($rows as $r => $row) {
            if ($r == 1) continue;

            if (trim((string)$row[$cKode]) === $kodeCustomer) {
                $namaCustomer = trim((string)$row[$cNama]);
                break;
            }
        }
    }


    $rows = $BARANG->toArray(null, true, true, true);

    $header = array_map("strtolower", array_map("trim", $rows[1] ?? []));
    $cHarga = findCol($header, "harga penyerahan");
    $cSeri  = findCol($header, "seri");
    $cQty   = findCol($header, "jumlah");
    $cSat   = findCol($header, "satuan");
    $cNet   = findCol($header, "netto");
    $cBru   = findCol($header, "bruto");
    $cCif   = findCol($header, "cif");
    $cKode  = findCol($header, "kode");
    $cUra   = findCol($header, "uraian");
    $cMer   = findCol($header, "merek");
    $cTip   = findCol($header, "tipe");
    $cUkr   = findCol($header, "ukuran");
    $cSpe   = findCol($header, "spesifikasi");
    $cJmlKemasanBarang  = findCol($header, "jumlah kemasan");
    $cKodeKemasanBarang = findCol($header, "kode kemasan");


    // ================= KEMASAN HEADER (UNTUK BC 27, 23, DLL) =================
    $jumlahPartsKemasan = [];
    $tipePartsKemasan   = [];

    if ($KEMASAN && !in_array($kodeDokumen, ["40", "41"])) {

        $rowsKemasan = $KEMASAN->toArray(null, true, true, true);
        $headerKemasan = array_map("strtolower", array_map("trim", $rowsKemasan[1] ?? []));

        $cKodeKem = findCol($headerKemasan, "kode");
        $cJmlKem  = findCol($headerKemasan, "jumlah");

        if ($cKodeKem !== null && $cJmlKem !== null) {
            for ($r = 2; $r <= count($rowsKemasan); $r++) {
                $row = $rowsKemasan[$r] ?? null;
                if (!$row) continue;

                $kode = trim((string)($row[$cKodeKem] ?? ""));
                $jml  = trim((string)($row[$cJmlKem] ?? ""));

                if ($kode === "" || $jml === "" || !is_numeric($jml)) continue;

                $jumlahPartsKemasan[] = $jml;
                $tipePartsKemasan[]   = strtoupper($kode);
            }
        }
    }


    


    /* ================= PREPARE INSERT ================= */
    $stmt = $koneksi->prepare("
        INSERT INTO excel_data (
            tanggal_masuk, nomor_aju, nomor_pendaftaran, tanggal_dokumen,
            dokumen_pelengkap, nama_customer,
            jumlah_kemasan, tipe_kemasan,
            nomor_seri_barang, kode_barang, nama_item, quantity_item, satuan_barang,
            netto, bruto, valuta, cif, ndpbm, harga_penyerahan,
            kode_tujuan_pengiriman, is_fallback_bruto, jenis_dokumen
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    


    for ($r = 2; $r <= count($rows); $r++) {
        $rw = $rows[$r] ?? null;
        if (!$rw || empty($rw[$cSeri])) continue;

        $brutoRaw = $rw[$cBru] ?? "";
        $bruto = ($brutoRaw == "" || $brutoRaw == 0) ? $fallbackBruto : (float)$brutoRaw;
        $isFallback = ($brutoRaw == "" || $brutoRaw == 0) ? 1 : 0;

        $namaItem = concatItemName([
            $rw[$cUra] ?? "", $rw[$cMer] ?? "", $rw[$cTip] ?? "", $rw[$cUkr] ?? "", $rw[$cSpe] ?? ""
        ]);

        // ================= KHUSUS DOKUMEN 40 & 41 =================
        if ($kodeDokumen === "40" || $kodeDokumen === "41") {

            // NDPBM wajib 1
            $ndpbmFinal = 1;

            // Ambil dari kolom HARGA PENYERAHAN
            $hargaPenyerahan = isset($rw[$cHarga]) ? (float)$rw[$cHarga] : 0;

            // CIF = Harga Penyerahan
            $cifFinal = $hargaPenyerahan;

        } else {

            // Dokumen normal
            $ndpbmFinal = (float)$ndpbm;
            $cifFinal  = isset($rw[$cCif]) ? (float)$rw[$cCif] : 0;

            // Harga Penyerahan = CIF x NDPBM
            $hargaPenyerahan = $cifFinal * $ndpbmFinal;
        }

        // ================= SET VALUTA =================
        if ($kodeDokumen === "40" || $kodeDokumen === "41") {
            $kodeValuta = "IDR";
        }
        // ================= KEMASAN PER BARANG =================
        $jumlahKemasanFinal = "";
        $tipeKemasanFinal   = "";

        if ($kodeDokumen === "40" || $kodeDokumen === "41") {

            // BC 40 & 41 → PER BARANG
            $jumlahKemasanFinal =
                $cJmlKemasanBarang !== null
                ? trim((string)($rw[$cJmlKemasanBarang] ?? ""))
                : "";

            $tipeKemasanFinal =
                $cKodeKemasanBarang !== null
                ? strtoupper(trim((string)($rw[$cKodeKemasanBarang] ?? "")))
                : "";

        } else {

            // BC 27, 23, dll → HEADER (HANYA BARIS PERTAMA)
            if ($r === 2) {
                $jumlahKemasanFinal = implode(" + ", $jumlahPartsKemasan);
                $tipeKemasanFinal   = implode(" + ", $tipePartsKemasan);
            } else {
                $jumlahKemasanFinal = "";
                $tipeKemasanFinal   = "";
            }
        }


        $stmt->bind_param(
            "ssssssssissisddsddisis",
            $tanggalMasuk,
            $nomorAju,
            $nomorPendaftaran,
            $tanggalDokumen,
            $dokumenPelengkap,
            $namaCustomer,
            $jumlahKemasanFinal,
            $tipeKemasanFinal,
            $rw[$cSeri],
            $rw[$cKode],
            $namaItem,
            $rw[$cQty],
            $rw[$cSat],
            $rw[$cNet],
            $bruto,
            $kodeValuta,
            $cifFinal,
            $ndpbmFinal,
            $hargaPenyerahan,
            $kodeTujuan,
            $isFallback,
            $jenisDokumen
        );

        if ($stmt->execute()) {
            $hasSuccess = true;
            $successCount++;
        } else {
            $errors[] = "❌ Gagal insert AJU $nomorAju : " . $stmt->error;
            $hasError = true;
        }

    }

    $stmt->close();
    $hasSuccess = true;
}

/* ============================================
   FINAL OUTPUT
============================================ */
$koneksi->close();

/* ===== PRIORITAS OUTPUT ===== */

if (!empty($errors)) {
    echo "ERROR:<br>" . implode("<br>", $errors);
    exit;
}

if ($hasSuccess && !empty($warnings)) {
    echo "INFO: Upload selesai dengan peringatan.<br>" . implode("<br>", $warnings);
    exit;
}

if (!empty($warnings)) {
    echo "WARNING:" . implode("<br>", $warnings);
    exit;
}

if ($hasSuccess) {
    echo "SUCCESS: Upload & ekstraksi selesai! ($successCount item)";
    exit;
}

echo "WARNING: Tidak ada data yang berhasil diproses.";

