/* =============================== NAV ACTIVE =============================== */
const currentPage = window.location.pathname.split("/").pop() || "index.html";

document.querySelectorAll(".navbar-nav .nav-link").forEach((link) => {
  const linkPage = link.getAttribute("href").split("/").pop();
  if (linkPage === currentPage) link.classList.add("active");
});

/* ================TOAST================ */
function showToast(message, type = "primary") {
  $("#mainToast")
    .removeClass()
    .addClass(`toast align-items-center text-bg-${type} border-0`);
  $("#toastMessage").html(message);
  new bootstrap.Toast(document.getElementById("mainToast"), {
    delay: 3000,
    autohide: true,
  }).show();
}

function showExportToast() {
  if ($("#exportToast").length === 0) {
    $("body").append(`
      <div id="exportToast" class="toast text-bg-info border-0"
        style="position:fixed; top:20px; right:20px; z-index:999999">
        <div class="d-flex">
          <div class="toast-body">⏳ Sedang membuat file Excel... Mohon tunggu...</div>
        </div>
      </div>
    `);
  }
  new bootstrap.Toast(document.getElementById("exportToast"), {
    autohide: false,
  }).show();
}
function hideExportToast() {
  const el = document.getElementById("exportToast");
  if (!el) return;
  bootstrap.Toast.getInstance(el)?.hide();
}

/* =============== FORMATTER =============== */
function formatMultiline(v) {
  if (!v) return "";
  return String(v).replace(/\n/g, "<br>");
}

function formatNumber(v) {
  if (v === null || v === undefined || v === "") return "";
  if (isNaN(v)) return v;
  const n = parseFloat(v);
  return Number.isInteger(n)
    ? n.toLocaleString("en-US")
    : n.toLocaleString("en-US", { maximumFractionDigits: 2 });
}

function formatWeight(v) {
  if (v === null || v === undefined || v === "") return "";
  if (isNaN(v)) return v;
  const n = parseFloat(v);
  return Number.isInteger(n)
    ? n.toLocaleString("en-US") + " Kg"
    : n.toLocaleString("en-US", { maximumFractionDigits: 2 }) + " Kg";
}

function formatNDPBM(v) {
  if (v === null || v === undefined || v === "") return "";
  if (isNaN(v)) return v;
  const n = parseFloat(v);
  return "Rp " + n.toLocaleString("en-US", { maximumFractionDigits: 2 });
}

function formatCurrencyAuto(value, currencyCode) {
  if (value === null || value === undefined || value === "") return "";
  if (isNaN(value)) return value;
  const num = parseFloat(value);
  const symbols = {
    IDR: "Rp ",
    USD: "$ ",
    EUR: "€ ",
    JPY: "¥ ",
    SGD: "S$ ",
    MYR: "RM ",
    GBP: "£ ",
    CNY: "¥ ",
    KRW: "₩ ",
    THB: "฿ ",
  };
  const prefix = symbols[(currencyCode || "").toUpperCase()] || "";
  return Number.isInteger(num)
    ? prefix + num.toLocaleString("en-US")
    : prefix + num.toLocaleString("en-US", { maximumFractionDigits: 2 });
}

/* ============== FORM: UPLOAD ============== */
$("#uploadForm").on("submit", function (e) {
  e.preventDefault();

  if (!$("input[name='files[]']").val()) {
    showToast("⚠️ File belum dipilih!", "danger");
    return;
  }

  const fd = new FormData(this);
  fetch("assets/php/upload.php", { method: "POST", body: fd })
    .then((r) => r.text())
    .then((msg) => {
      if (msg.startsWith("ERROR:")) {
        $("#uploadForm")[0].reset();
        setDefaultToday();
        showToast(msg.replace("ERROR:", ""), "danger");
        return;
      }
      if (msg.startsWith("INFO:"))
        return showToast(msg.replace("INFO:", ""), "info");
      if (msg.startsWith("WARNING:"))
        return showToast(msg.replace("WARNING:", ""), "warning");
      if (msg.startsWith("SUCCESS:")) {
        showToast(msg.replace("SUCCESS:", ""), "success");
        $("#uploadForm")[0].reset();
        setDefaultToday();
        loadTable();
        return;
      }
      showToast(msg, "primary");
    })
    .catch(() => showToast("Gagal upload file.", "danger"));
});

/* =============================== FORM: UPDATE =============================== */
$("#modalUpdateForm").on("submit", function (e) {
  e.preventDefault();

  if (!$("input[name='file_update']").val()) {
    showToast("⚠️ Pilih file update dahulu!", "danger");
    return;
  }

  const fd = new FormData(this);
  fetch("assets/php/update_date.php", { method: "POST", body: fd })
    .then((r) => r.text())
    .then((msg) => {
      showToast(msg, "info");
      loadTable();
    })
    .catch(() => showToast("Gagal update tanggal.", "danger"));
});

/* =============================== FORM: DELETE RANGE =============================== */
$("#modalDeleteForm").on("submit", function (e) {
  e.preventDefault();

  const s = $("#delStartModal").val();
  const e2 = $("#delEndModal").val();

  if (!s || !e2) {
    showToast("⚠️ Tolong isi tanggal dengan lengkap!", "danger");
    return;
  }

  fetch(`assets/php/delete_range.php?start=${s}&end=${e2}`)
    .then((r) => r.text())
    .then((msg) => {
      showToast(msg, "danger");
      loadTable();
    })
    .catch(() => showToast("Gagal menghapus data rentang tanggal.", "danger"));
});
/* =============================== UPDATE PER AJU =============================== */
function openUpdateTanggal(nomorAju, tanggalMasuk) {
  $("#updateNomorAju").val(nomorAju);
  $("#updateTanggalMasuk").val(tanggalMasuk);

  const modal = new bootstrap.Modal(
    document.getElementById("updateTanggalModal")
  );
  modal.show();
}
$("#updateTanggalForm").on("submit", function (e) {
  e.preventDefault();

  const fd = new FormData(this);

  fetch("assets/php/update_tanggal_per_dokumen.php", {
    method: "POST",
    body: fd,
  })
    .then((r) => r.text())
    .then((msg) => {
      showToast(msg, "success");
      $("#updateTanggalModal").modal("hide");
      loadTable();
    })
    .catch(() => showToast("Gagal update tanggal masuk.", "danger"));
});

/* =============================== DELETE PER AJU =============================== */
function deleteDocument(aju) {
  if (!confirm("Hapus semua data dokumen ini?")) return;

  fetch("assets/php/delete.php?aju=" + encodeURIComponent(aju))
    .then((r) => r.text())
    .then((msg) => {
      showToast(msg, "danger");
      loadTable();
    })
    .catch(() => showToast("Gagal menghapus dokumen.", "danger"));
}

/* =============================== DATATABLE =============================== */
let table = null;

function loadTable() {
  if (table) {
    table.destroy();
    $("#excelTable tbody").empty();
  }

  table = $("#excelTable").DataTable({
    dom:
      "<'dt-controls row mb-2'<'col-sm-6'l><'col-sm-6 d-flex justify-content-end'f>>" +
      "t" +
      "<'dt-pagination row mt-2'<'col-sm-12 d-flex justify-content-center'p>>",
    ordering: false,
    pageLength: 100,
    autoWidth: false,
    initComplete: function () {
      $(".dataTables_filter input").attr("placeholder", "Type Something...");
      $(".dataTables_filter label").css({
        display: "flex",
        alignItems: "center",
        gap: "8px",
        margin: "0",
      });
    },
    ajax: {
      url: "assets/php/fetch_data.php",
      dataSrc: function (d) {
        if (!Array.isArray(d)) return [];
        d.sort((a, b) => new Date(a.tanggal_masuk) - new Date(b.tanggal_masuk));

        const grouped = {};
        d.forEach((row) => {
          const key = row.nomor_aju || "_NO_AJU_";
          if (!grouped[key]) grouped[key] = [];
          grouped[key].push(row);
        });

        const rows = [];
        let docCounter = 1;

        Object.keys(grouped).forEach((aju) => {
          const groupRows = grouped[aju];

          // 🔥 gabungkan semua text untuk search
          const searchBlob = groupRows
            .map((r) =>
              [
                r.nomor_aju,
                r.nomor_pendaftaran,
                r.nama_customer,
                r.kode_barang,
                r.nama_item,
                r.dokumen_pelengkap,
                r.tipe_kemasan,
                r.tujuan_pengiriman,
              ]
                .filter(Boolean)
                .join(" ")
            )
            .join(" ");

          groupRows.forEach((r, idx) => {
            const row = { ...r };
            row.row_item_index = idx;
            row.doc_no = docCounter;
            row.search_blob = searchBlob; // 🔥 penting
            rows.push(row);
          });

          docCounter++;
        });

        return rows;
      },
    },

    columns: [
      {
        data: "doc_no",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "jenis_dokumen",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "tanggal_masuk",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "nomor_aju",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "nomor_pendaftaran",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "tanggal_dokumen",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "dokumen_pelengkap",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "nama_customer",
        render: (d, t, r) => (r.row_item_index === 0 ? d : ""),
      },
      {
        data: "jumlah_kemasan",
        render: (d, t, r) => {
          const is40or41 =
            r.jenis_dokumen &&
            (r.jenis_dokumen.includes("40") || r.jenis_dokumen.includes("41"));

          // 🔥 BC 40 / 41 → tampil per baris
          if (is40or41) {
            return formatNumber(d);
          }

          // 🔁 dokumen lain → hanya baris pertama
          return r.row_item_index === 0 ? formatNumber(d) : "";
        },
      },

      {
        data: "tipe_kemasan",
        render: (d, t, r) => {
          const is40or41 =
            r.jenis_dokumen &&
            (r.jenis_dokumen.includes("40") || r.jenis_dokumen.includes("41"));

          // 🔥 BC 40 / 41 → tampil per baris
          if (is40or41) {
            return d || "";
          }

          // 🔁 dokumen lain → hanya baris pertama
          return r.row_item_index === 0 ? d : "";
        },
      },

      { data: "quantity_item", render: (d) => formatNumber(d) },
      { data: "satuan_barang" },
      { data: "kode_barang" },
      { data: "nama_item" },
      { data: "nomor_seri_barang", render: (d) => d || "" },
      { data: "netto", render: (d) => formatWeight(d) },

      {
        data: "bruto",
        render: function (data, type, row) {
          if (type !== "display") return data;
          if (row.is_fallback_bruto == 1) {
            return row.row_item_index === 0 ? formatWeight(data) : "";
          }
          return formatWeight(data);
        },
      },

      { data: "valuta" },
      {
        data: "cif",
        render: (d, t, r) => formatCurrencyAuto(d, r.valuta),
      },
      { data: "ndpbm", render: (d) => formatNDPBM(d) },
      {
        data: "harga_penyerahan",
        render: (d) => formatCurrencyAuto(d, "IDR"),
      },

      { data: "kode_tujuan_pengiriman" },

      {
        data: "kode_tujuan_pengiriman",
        render: (d) => {
          const map = {
            1: "PENYERAHAN BKP",
            2: "PENYERAHAN JKP",
            3: "RETUR",
            4: "NON PENYERAHAN",
            5: "LAINNYA",
          };
          return d ? `${map[d] || "TIDAK DIKETAHUI"}` : "";
        },
      },

      {
        data: null,
        orderable: false,
        searchable: false,
        render: (d, t, r) =>
          r.row_item_index === 0
            ? `
              <div class="d-flex gap-1">
                <button 
                  class="btn btn-warning btn-sm"
                  onclick="openUpdateTanggal('${r.nomor_aju}', '${
                r.tanggal_masuk || ""
              }')"
                >
                  Update
                </button>
                <button 
                  class="btn btn-danger btn-sm"
                  onclick="deleteDocument('${r.nomor_aju}')"
                >
                  Delete
                </button>
              </div>
            `
            : "",
      },
      {
        data: "search_blob",
        visible: false,
        searchable: true,
      },
    ],
  });

  const dtWrapper = $("#excelTable_wrapper");
  const topControls = dtWrapper.find(".dt-controls");
  const bottomPaginate = dtWrapper.find(".dt-pagination");

  $("#dt-top-controls").empty().append(topControls);
  $("#dt-bottom-pagination").empty().append(bottomPaginate);
}

loadTable();

/* =============================== EXPORT EXCELJS =============================== */
document.getElementById("excelJSBtn").onclick = generateExcelJS;

async function generateExcelJS() {
  showExportToast();

  try {
    const res = await fetch("assets/php/fetch_data.php", {
      headers: { "Cache-Control": "no-cache" },
    });

    const raw = await res.text();
    const data = JSON.parse(raw);

    if (!Array.isArray(data) || data.length === 0) {
      alert("❌ Tidak ada data untuk diexport!");
      hideExportToast();
      return;
    }

    // 🔹 Group data berdasarkan jenis dokumen
    const groupedByJenis = {};
    data.forEach((r) => {
      const jenis = r.jenis_dokumen || "UNKNOWN";
      if (!groupedByJenis[jenis]) groupedByJenis[jenis] = [];
      groupedByJenis[jenis].push(r);
    });

    const wb = new ExcelJS.Workbook();

    const headers = [
      "No",
      "Tanggal Masuk",
      "Nomor AJU",
      "No Pendaftaran",
      "Tanggal Dokumen",
      "Dokumen Pelengkap",
      "Nama Customer",
      "Jumlah Kemasan",
      "Tipe Kemasan",
      "Qty",
      "Satuan",
      "Kode Barang",
      "Nama Item",
      "Nomor Seri",
      "NW",
      "GW",
      "Valuta",
      "CIF",
      "NDPBM",
      "Harga Penyerahan",
      "Kode Tujuan",
      "Tujuan Pengiriman",
      "Barang modal Y/T",
      "Keterangan",
    ];

    for (const jenis in groupedByJenis) {
      const sheet = wb.addWorksheet(jenis.substring(0, 31));
      const rows = groupedByJenis[jenis];

      sheet.addRow(headers);
      sheet.getRow(1).eachCell((c) => {
        c.font = { bold: true, color: { argb: "FFFFFFFF" } };
        c.fill = {
          type: "pattern",
          pattern: "solid",
          fgColor: { argb: "FF1B2A49" },
        };
        c.alignment = { horizontal: "center" };
      });

      let no = 1;

      // 🔹 Group per nomor AJU
      const groupedByAju = {};
      rows.forEach((r) => {
        const aju = r.nomor_aju || "_NO_AJU_";
        if (!groupedByAju[aju]) groupedByAju[aju] = [];
        groupedByAju[aju].push(r);
      });

      // 🔹 Loop tiap nomor AJU
      for (const aju in groupedByAju) {
        const groupRows = groupedByAju[aju];

        groupRows.forEach((r, idx) => {
          const first = idx === 0; // ⚡ first hanya baris pertama per AJU
          const is40or41 =
            r.jenis_dokumen &&
            (r.jenis_dokumen.includes("40") || r.jenis_dokumen.includes("41"));

          let brutoExport;
          if (r.is_fallback_bruto == 1) {
            brutoExport = first
              ? Number(String(r.bruto).replace(/[^0-9.-]/g, "")) || 0
              : "";
          } else {
            brutoExport = Number(String(r.bruto).replace(/[^0-9.-]/g, "")) || 0;
          }

          const tujuan = (r.kode_tujuan_pengiriman || "").toUpperCase();
          const kodeBarang = String(r.kode_barang || "").trim();
          let keteranganExport = "";

          if (tujuan === "3") {
            keteranganExport = "BARANG JADI";
          } else if (
            tujuan === "5" &&
            (kodeBarang === "1114433" || kodeBarang === "KEMASAN")
          ) {
            keteranganExport = "BARANG PEMBANTU";
          } else if (tujuan === "1") {
            keteranganExport = "BAHAN BAKU";
          } else if (
            tujuan === "5" &&
            (kodeBarang != "1114433" || kodeBarang != "KEMASAN")
          ) {
            keteranganExport = "BAHAN BAKU";
          }

          const barangModal = keteranganExport.toUpperCase().includes("MESIN")
            ? "Y"
            : "T";

          const tujuanMap = {
            1: "PENYERAHAN BKP",
            2: "PENYERAHAN JKP",
            3: "RETUR",
            4: "NON PENYERAHAN",
            5: "LAINNYA",
          };
          const tujuanTrans = tujuanMap[r.kode_tujuan_pengiriman] || "";

          const rowExcel = sheet.addRow([
            first ? no++ : "",
            first ? r.tanggal_masuk : "",
            first ? r.nomor_aju : "",
            first ? r.nomor_pendaftaran : "",
            first ? r.tanggal_dokumen : "",
            first ? r.dokumen_pelengkap : "",
            first ? r.nama_customer : "",
            is40or41
              ? r.jumlah_kemasan || ""
              : first
              ? r.jumlah_kemasan || ""
              : "",

            is40or41 ? r.tipe_kemasan || "" : first ? r.tipe_kemasan || "" : "",

            Number(r.quantity_item) || 0,
            r.satuan_barang || "",
            r.kode_barang || "",
            r.nama_item || "",
            Number(r.nomor_seri_barang) || 0,

            Number(String(r.netto).replace(/[^0-9.-]/g, "")) || 0,
            brutoExport,

            r.valuta || "",
            Number(String(r.cif).replace(/[^0-9.-]/g, "")) || 0,
            Number(String(r.ndpbm).replace(/[^0-9.-]/g, "")) || 0,
            Number(String(r.harga_penyerahan).replace(/[^0-9.-]/g, "")) || 0,

            r.kode_tujuan_pengiriman === null || r.kode_tujuan_pengiriman === ""
              ? ""
              : r.kode_tujuan_pengiriman,
            tujuanTrans,
            barangModal,
            keteranganExport,
          ]);

          // 🔴 Warnai baris
          if (tujuan === "3" || tujuan === "5") {
            rowExcel.eachCell((cell) => {
              cell.font = { color: { argb: "FFFF0000" } };
            });
          }
        });
      }

      // 🔹 Auto align & auto fit
      sheet.eachRow((row) => {
        row.eachCell((cell) => {
          cell.alignment = {
            horizontal: "center",
            vertical: "middle",
            wrapText: false,
          };
        });
      });

      sheet.columns.forEach((col) => {
        let maxLength = 11;
        col.eachCell({ includeEmpty: true }, (cell) => {
          const v = cell.value ? cell.value.toString() : "";
          maxLength = Math.max(maxLength, v.length + 2);
        });
        col.width = maxLength;
      });
      const GW_COL = 16; // kolom GW (1-based)

      let startRow = null;

      sheet.eachRow((row, rowNumber) => {
        if (rowNumber === 1) return;

        const gw = row.getCell(GW_COL).value;
        const hasValue = gw !== null && gw !== "" && gw !== undefined;

        if (hasValue) {
          // Tutup merge sebelumnya
          if (startRow !== null && rowNumber - 1 > startRow) {
            sheet.mergeCells(startRow, GW_COL, rowNumber - 1, GW_COL);
          }
          // Mulai merge baru
          startRow = rowNumber;
        }
      });

      // Merge terakhir
      if (startRow !== null && sheet.rowCount > startRow) {
        sheet.mergeCells(startRow, GW_COL, sheet.rowCount, GW_COL);
      }
    }

    // 🔹 Export file
    const buf = await wb.xlsx.writeBuffer();
    saveAs(
      new Blob([buf], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      }),
      "Laporan_BCP_BC27.xlsx"
    );

    showToast("✔️ Export Excel selesai!", "success");
  } catch (err) {
    console.error(err);
    alert("❌ Error saat export!");
  }

  hideExportToast();
}

/* =============================== DATE INPUT AUTO SHOW =============================== */
document.querySelectorAll("input[type='date']").forEach((input) => {
  input.addEventListener("click", function () {
    if (this.showPicker) this.showPicker();
  });
});

/* =============================== SET DEFAULT TODAY =============================== */
function setDefaultToday() {
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, "0");
  const dd = String(today.getDate()).padStart(2, "0");

  const formatted = `${yyyy}-${mm}-${dd}`;

  document.querySelectorAll("input[name='tanggal_masuk']").forEach((el) => {
    if (!el.value) el.value = formatted;
  });
}

document.addEventListener("DOMContentLoaded", setDefaultToday);
