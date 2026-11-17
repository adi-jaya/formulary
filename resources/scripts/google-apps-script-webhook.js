/**
 * Webhook Google Apps Script untuk Sistem FormulariumRS (v1.2.0)
 * Rumah Sakit Aisyiyah Siti Fatimah Tulangan
 *
 * Panduan Pemasangan:
 * 1. Buka Google Spreadsheet Rekapitulasi:
 *    https://docs.google.com/spreadsheets/d/1TTdhIy9Bbc0eQYAlm_jqqcizgfJjJsogkCZZBmJOFY8/edit?gid=1370878207
 * 2. Klik menu "Extensions" > "Apps Script".
 * 3. Hapus seluruh kode lama di editor, lalu salin dan tempel SELURUH skrip ini.
 * 4. Pastikan baris ke-1 pada tab sheet tidak lagi berisi formula =IMPORTDATA(...)
 *    (jika sebelumnya ada formula di sel A1, hapus terlebih dahulu agar tidak terjadi tabrakan array #REF!).
 * 5. Klik menu "Deploy" > "Manage deployments" (atau "New deployment").
 * 6. Pilih tipe "Web app":
 *    - Description: Webhook Real-Time FormulariumRS
 *    - Execute as: "Me" (email Google Anda)
 *    - Who has access: "Anyone" (Siapa saja / Anonim)  <-- PENTING! Jangan pilih "Only myself"
 * 7. Klik "Deploy" (atau pilih "New version" jika mengedit deployment lama).
 * 8. Jika muncul jendela otorisasi "Authorization required":
 *    - Klik "Review Permissions"
 *    - Pilih akun Google Anda
 *    - Klik "Advanced" (Lanjutan) di bagian bawah
 *    - Klik "Go to Untitled project (unsafe)"
 *    - Klik "Allow" (Izinkan)
 * 9. Salin "Web app URL" (akhiran /exec) dan pastikan sesuai dengan FORMULARY_SHEET_WEBHOOK_URL di berkas .env.
 */

// Token rahasia webhook (wajib identik dengan FORMULARY_SHEET_WEBHOOK_SECRET di .env)
const WEBHOOK_SECRET = "15460c08093708d020c8765197184fe5";

// Nama tab lembar kerja tujuan (sesuai gid: 1370878207)
const TARGET_SHEET_NAME = "Rekapitulasi Usulan Obat Uji Coba";

// 12 Kolom Kanonikal FormulariumRS v1.2.0
const CANONICAL_HEADERS = [
  "No",
  "Tanggal",
  "Tahun Periode",
  "Nama Dokter",
  "Nama Obat",
  "Nama Principle",
  "Alasan Klinis",
  "Catatan Pengusul",
  "Waktu Input",
  "Status Telaah",
  "Catatan Telaah",
  "Penelaah"
];

/**
 * Endpoint GET untuk verifikasi ketersediaan dan pengujian koneksi via browser / curl.
 */
function doGet(e) {
  return ContentService.createTextOutput(JSON.stringify({
    status: "ok",
    message: "FormulariumRS Webhook is active and reachable",
    target_sheet: TARGET_SHEET_NAME,
    timestamp: new Date().toISOString()
  })).setMimeType(ContentService.MimeType.JSON);
}

/**
 * Endpoint POST penerima data webhook real-time dari Laravel (< 2 detik).
 */
function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return jsonResponse({ status: "error", message: "Payload kosong atau tidak valid" });
    }

    const payload = JSON.parse(e.postData.contents);

    // Verifikasi shared secret
    if (!payload || payload.secret !== WEBHOOK_SECRET) {
      return jsonResponse({ status: "error", message: "Unauthorized: Invalid secret" });
    }

    // Ping test
    if (payload.action === "ping") {
      return jsonResponse({ status: "success", message: "Pong" });
    }

    const sheet = resolveTargetSheet(payload.sheet_name);

    // Aksi 1: Pembersihan Baris Data (Baris ke-2 ke bawah, Header Baris 1 dipertahankan/dibuat)
    if (payload.action === "clear_sheet") {
      ensureHeaderRow(sheet);
      const lastRow = sheet.getLastRow();
      const lastCol = Math.max(sheet.getLastColumn(), CANONICAL_HEADERS.length);

      if (lastRow > 1) {
        sheet.getRange(2, 1, lastRow - 1, lastCol).clearContent();
      }

      return jsonResponse({ status: "success", message: "Sheet cleared below header" });
    }

    // Aksi 2: Penambahan Baris Baru (12 Kolom Kanonikal)
    if (payload.action === "append_rows" && Array.isArray(payload.rows)) {
      ensureHeaderRow(sheet);

      const rowsToAdd = payload.rows.map(r => [
        r.no,
        r.proposal_date,
        r.period_year,
        r.doctor_name,
        r.medicine_name,
        r.principal_name,
        r.clinical_justification,
        r.notes,
        r.created_at,
        r.review_status,
        r.review_notes,
        r.reviewer_name
      ]);

      if (rowsToAdd.length > 0) {
        const startRow = sheet.getLastRow() + 1;
        sheet.getRange(startRow, 1, rowsToAdd.length, CANONICAL_HEADERS.length).setValues(rowsToAdd);
      }

      return jsonResponse({ status: "success", count: rowsToAdd.length });
    }

    return jsonResponse({ status: "error", message: "Invalid action" });
  } catch (err) {
    return jsonResponse({ status: "error", message: err.toString() });
  }
}

/**
 * Mencari tab sheet tujuan berdasarkan nama atau fallback ke TARGET_SHEET_NAME / active sheet.
 */
function resolveTargetSheet(customName) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  if (customName) {
    const s = ss.getSheetByName(customName);
    if (s) return s;
  }
  const target = ss.getSheetByName(TARGET_SHEET_NAME);
  if (target) return target;
  return ss.getActiveSheet();
}

/**
 * Memastikan baris ke-1 selalu berisi 12 Header Kanonikal jika sheet masih kosong.
 */
function ensureHeaderRow(sheet) {
  if (sheet.getLastRow() === 0) {
    sheet.getRange(1, 1, 1, CANONICAL_HEADERS.length).setValues([CANONICAL_HEADERS]);
  }
}

/**
 * Helper pembentuk respons JSON.
 */
function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
