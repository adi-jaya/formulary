/**
 * Webhook Google Apps Script untuk Sistem FormulariumRS
 * Pasang pada Google Spreadsheet: Extensions > Apps Script > Deploy as Web App
 */
const WEBHOOK_SECRET = "GANTI_DENGAN_FORMULARY_SHEET_WEBHOOK_SECRET_ANDA";

function doPost(e) {
  try {
    const payload = JSON.parse(e.postData.contents);
    if (!payload || payload.secret !== WEBHOOK_SECRET) {
      return ContentService.createTextOutput(JSON.stringify({ status: "error", message: "Unauthorized" }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();

    // Aksi 1: Pembersihan Baris Data (Baris ke-2 ke bawah, Header Baris 1 tetap utuh)
    if (payload.action === "clear_sheet") {
      const lastRow = sheet.getLastRow();
      if (lastRow > 1) {
        sheet.getRange(2, 1, lastRow - 1, sheet.getLastColumn()).clearContent();
      }
      return ContentService.createTextOutput(JSON.stringify({ status: "success", message: "Sheet cleared below header" }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    // Aksi 2: Penambahan Baris Baru (12 Kolom Kanonikal)
    if (payload.action === "append_rows" && Array.isArray(payload.rows)) {
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
        sheet.getRange(sheet.getLastRow() + 1, 1, rowsToAdd.length, rowsToAdd[0].length).setValues(rowsToAdd);
      }

      return ContentService.createTextOutput(JSON.stringify({ status: "success", count: rowsToAdd.length }))
        .setMimeType(ContentService.MimeType.JSON);
    }

    return ContentService.createTextOutput(JSON.stringify({ status: "error", message: "Invalid action" }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (err) {
    return ContentService.createTextOutput(JSON.stringify({ status: "error", message: err.toString() }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
