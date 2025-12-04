<?php
require __DIR__ . '/../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$koneksi = new mysqli("localhost", "root", "", "excel_data");
if ($koneksi->connect_error) {
    die("Koneksi gagal: " . $koneksi->connect_error);
}

// Fungsi normalisasi teks / angka
function normalizeText($value) {
    if (is_null($value)) return "";
    if (is_numeric($value)) {
        return preg_replace('/\.0+$/', '', (string)$value);
    }
    return trim((string)$value);
}

if (!isset($_FILES['file_update'])) {
    echo "File tidak ditemukan.";
    exit;
}

$tmp = $_FILES['file_update']['tmp_name'];
$spreadsheet = IOFactory::load($tmp);
$sheet = $spreadsheet->getActiveSheet();

// Ambil semua data beserta object cell
$rows = $sheet->toArray(null, true, true, true);

// Header
$header = array_map("strtolower", $rows[1]);

$colNoPend = array_search("no_pendaftaran", $header);
$colTanggal = array_search("tanggal_masuk", $header);

if ($colNoPend === false || $colTanggal === false) {
    echo "❌ Kolom 'no_pendaftaran' atau 'tanggal_masuk' tidak ditemukan!";
    exit;
}

$updated = 0;
$notFound = [];

$rowCount = count($rows);

for ($i = 2; $i <= $rowCount; $i++) {

    $noPend = normalizeText($rows[$i][$colNoPend]);
    if ($noPend == "") continue;

    // === FIX TANGGAL ===
    $cell = $sheet->getCell($colTanggal . $i);
    $rawVal = $cell->getValue();
    $tglMasuk = "";

    // Jika format tanggal Excel (numeric)
    if (is_numeric($rawVal) && Date::isDateTime($cell)) {
        $tglMasuk = Date::excelToDateTimeObject($rawVal)->format("Y-m-d");

    // Jika string (manual input)
    } elseif (is_string($rawVal)) {
        $time = strtotime($rawVal);
        if ($time !== false) {
            $tglMasuk = date("Y-m-d", $time);
        } else {
            // fallback jika format benar-benar aneh
            $tglMasuk = null;
        }
    }

    // Jika tetap gagal parse → skip row
    if (!$tglMasuk) {
        continue;
    }

    // cek apakah nomor pendaftaran ada di DB
    $check = $koneksi->prepare("SELECT COUNT(*) FROM excel_data WHERE TRIM(nomor_pendaftaran) = ?");
    $check->bind_param("s", $noPend);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    if ($count == 0) {
        $notFound[] = $noPend;
        continue;
    }

    // update tanggal masuk
    $stmt = $koneksi->prepare("
        UPDATE excel_data
        SET tanggal_masuk = ?
        WHERE TRIM(nomor_pendaftaran) = ?
    ");
    $stmt->bind_param("ss", $tglMasuk, $noPend);
    $stmt->execute();
    $stmt->close();

    $updated++;
}

// Output hasil
echo "<div style='padding:10px;background:#d8f6ff;border-radius:8px'>";
echo "<h4>Update selesai!</h4>";
echo "Jumlah data berhasil diupdate: <b>$updated</b><br><br>";

if (!empty($notFound)) {
    echo "<b>Nomor Pendaftaran tidak ditemukan:</b><br>";
    foreach ($notFound as $x) {
        echo "- $x<br>";
    }
}
echo "</div>";
?>
