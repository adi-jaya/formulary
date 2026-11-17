# Design Document: FormulariumRS v1.2.0 — Modul Telaah KFT Farmasi & Sinkronisasi Real-Time Google Sheets

**Tanggal:** 2026-11-16  
**Target Rilis:** `v1.2.0`  
**Status:** Disetujui (Builder Review Passed: Ready for Tier 2 Implementation Plan)  
**Dokumen Terkait:** [ROADMAP.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/ROADMAP.md), [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md)

---

## 1. Intent & The Why

Rilis **v1.2.0** melengkapi siklus kerja evaluasi formularium obat di RS Aisyiyah Siti Fatimah Tulangan dengan dua kapabilitas strategis:
1. **Digitalisasi Keputusan Sidang Komite Farmasi dan Terapi (KFT):** Selama ini penetapan status usulan obat (apakah disetujui, ditolak, atau diarahkan ke substitusi formularium) dicatat secara terpisah di lembar manual. Dibutuhkan kemampuan bagi tim Farmasi untuk mencatat status telaah dan catatan pertimbangan KFT langsung pada antarmuka web rekapitulasi secara aman tanpa mengubah prinsip formulir dokter yang *zero-friction* (bebas login/PIN).
2. **Sinkronisasi Google Sheets Real-Time Tanpa Jeda (*Zero Latency*):** Rumus bawaan Google Sheets `=IMPORTDATA(...)` memiliki jeda cache hingga 1–2 jam dari server Google. Rumah sakit membutuhkan data usulan dokter yang baru saja dikirimkan langsung muncul di Google Spreadsheet Farmasi dalam hitungan **< 2 detik** (*real-time event-driven push*) tanpa membebani kecepatan browsing dokter di smartphone.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
- **Modul Status Telaah KFT Internal Farmasi & Transparansi Klinis:**
  - Migrasi aditif pada tabel `proposal_items`: kolom `review_status` (*pending*, *approved*, *rejected*, *substituted* — default: `pending`), `review_notes`, `reviewed_at`, dan `reviewer_name`.
  - **Visibilitas Transparan Klinis (Opsi A):** Seluruh pengguna/dokter dapat melihat badge status telaah (`review_status`) dan teks catatan pertimbangan KFT (`review_notes`) secara publik di halaman rekapitulasi (`/recap`), sehingga dokter pengusul mengetahui alasan keputusan KFT. Namun, tindakan untuk **mengubah status** dan **mengedit catatan** wajib melalui autentikasi passcode sesi Farmasi.
  - Otorisasi Sesi Farmasi berbasis Passcode (`FORMULARY_REVIEW_SECRET`): Form pengajuan dokter di `/` tetap 100% bebas login. Pengubahan status di halaman rekapitulasi dilindungi modal input passcode sekali per sesi browser (`session(['kft_authenticated' => true])`).
  - Endpoint AJAX / Form `PATCH /proposals/items/{item}/review` untuk memperbarui status dan catatan telaah secara inline disertai umpan balik visual instan.
- **Safeguard Google Sheets Anti `#REF!` & Ekspor Lengkap:**
  - URL feed default `/exports/proposals.csv` tetap mempertahankan kontrak 8 kolom lama agar formula spreadsheet pasif yang sudah berjalan tidak mengalami *array spill conflict*.
  - Menambahkan query parameter opt-in `&include_review=1` pada CSV untuk membuka 4 kolom tambahan telaah bagi spreadsheet khusus KFT.
  - Ekspor Excel (`/exports/proposals.xlsx`) secara otomatis menyertakan 12 kolom kanonikal lengkap dengan format visual zamrud dan lebar kolom optimal.
- **Sinkronisasi Real-Time Google Sheets Tanpa Jeda (< 2 Detik):**
  - Mengirim data usulan baru secara instan ke Google Apps Script Webhook pada Google Spreadsheet Farmasi menggunakan helper Laravel 12 [`defer()`](https://laravel.com/docs/12.x/helpers#deferred-functions) di `ProposalController::store()`.
  - Waktu muat (latensi) di peramban/smartphone dokter pengusul tetap 0 ms karena pengiriman webhook berjalan pasca-penutupan koneksi HTTP respons.
  - Menggunakan layanan `GoogleSheetSyncService` berbasis Laravel HTTP Client dengan timeout 5 detik dan retry 2 kali.
- **Command Artisan Rekonsiliasi & Sinkronisasi Massal:**
  - `php artisan formulary:sync-sheets {--year= : Filter tahun periode} {--clear : Bersihkan lembar baris ke-2 ke bawah sebelum menulis ulang}` untuk inisialisasi awal Google Sheet baru atau penulisan ulang seluruh data secara berkala.
  - Dilengkapi konfirmasi interaktif untuk mencegah duplikasi data jika dijalankan tanpa opsi `--clear`.
- **Template Google Apps Script Resmi:**
  - Menyediakan berkas skrip Apps Script siap pakai (`resources/scripts/google-apps-script-webhook.js`) yang mengenali aksi `append_rows` dan `clear_sheet` (membersihkan baris ke-2 ke bawah dengan tetap mempertahankan baris header ke-1).

### Non-Goals
- Tidak membangun sistem autentikasi user/password bertingkat dengan tabel `users` (tetap berpegang pada prinsip *lean architecture* berbasis passcode sesi rahasia).
- Tidak memicu pencarian/pembaruan baris spreadsheet satu per satu saat apoteker mengubah status telaah di web (sesuai Opsi B yang dipilih pengguna: *Append-Only Push* untuk usulan baru dokter, sedangkan status telaah diperbarui via sinkronisasi massal berkala atau ekspor CSV/XLSX).
- Tidak menggunakan antrean Queue Worker daemon (Redis/Supervisor) yang menambah dependensi server.

### Anti-Goals
- **Dilarang memasang authentication guard atau PIN pada form pengajuan dokter (`GET /` dan `POST /proposals`).**
- **Dilarang mengubah urutan atau struktur default 8 kolom pada `/exports/proposals.csv` tanpa parameter opt-in.**
- **Dilarang menggagalkan transaksi penyimpanan dokter di database jika webhook Google Sheets mengalami timeout atau koneksi internet Google terputus.**

---

## 3. Constraints

- **Prinsip AGENTS.md:**
  - Wajib `declare(strict_types=1);` di setiap berkas PHP baru.
  - Mengikuti pola Controller ➔ Service ➔ Query Object / Model.
  - Menggunakan fungsi perbandingan string waktu-konstan `hash_equals()` untuk memvalidasi token webhook dan passcode telaah KFT.
  - Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS.
  - Standarisasi format kode Laravel Pint (`./vendor/bin/pint --test`) wajib bersih.

---

## 4. Approaches

### Opsi yang Dipertimbangkan:
1. **Opsi 1: Lightweight Service via Laravel 12 `defer()` & Sesi Passcode KFT (Dipilih)**
   - Pemicu instan via `defer()` tepat setelah `DB::transaction` sukses di `ProposalController::store()`.
   - Pola *Append-Only* ke Google Apps Script Webhook.
   - Otorisasi sesi berbasis `FORMULARY_REVIEW_SECRET` di `.env`.
   - *Alasan Pemilihan:* Memberikan sinkronisasi real-time instan (< 2 detik), bebas ketergantungan antrean Redis/Supervisor, dan menjaga alur dokter 100% *zero-friction*.

2. **Opsi 2: Antrean Latar Belakang Queue Worker (Redis / Database Queue)**
   - Mengirim job antrean asynchronous via `php artisan queue:work`.
   - *Alasan Ditolak:* Membutuhkan proses background daemon terpisah yang harus dipantau Supervisor. Jika worker mati di server lokal RS, webhook berhenti. Laravel 12 `defer()` menyelesaikan tugas ini jauh lebih elegan.

3. **Opsi 3: Dual-Event Push dengan Update Baris Individual di Google Sheets**
   - Mengirim pencarian baris dan update sel Google Sheets setiap kali status telaah diubah.
   - *Alasan Ditolak:* Sesuai keputusan Opsi B pengguna, pola *Append-Only* jauh lebih cepat, stabil, dan bebas risiko timeout Apps Script akibat pencarian baris panjang.

---

## 5. Detailed Architecture & Design Decisions

### 5.1 Skema Basis Data & Migrasi Aditif

Berkas migrasi: `database/migrations/YYYY_MM_DD_000001_add_review_columns_to_proposal_items_table.php`:
```php
public function up(): void
{
    Schema::table('proposal_items', function (Blueprint $table): void {
        $table->string('review_status', 32)->default('pending')->index();
        $table->text('review_notes')->nullable();
        $table->timestamp('reviewed_at')->nullable();
        $table->string('reviewer_name')->nullable();
    });
}

public function down(): void
{
    Schema::table('proposal_items', function (Blueprint $table): void {
        $table->dropIndex(['review_status']);
        $table->dropColumn(['review_status', 'review_notes', 'reviewed_at', 'reviewer_name']);
    });
}
```

Pembaruan pada model `ProposalItem.php`:
- Menambahkan kolom baru ke dalam `$fillable`.
- Menambahkan casting: `'reviewed_at' => 'datetime'`.

### 5.2 Konfigurasi Aplikasi (`config/formulary.php`)

Menambahkan variabel konfigurasi integrasi:
```php
return [
    'doctor_api_url' => env('FORMULARY_DOCTOR_API_URL', 'https://portalsifast.rsaisyiyahsitifatimah.com/api/dokter'),
    'doctor_api_timeout' => (int) env('FORMULARY_DOCTOR_API_TIMEOUT', 10),
    'doctor_cache_ttl_hours' => (int) env('FORMULARY_DOCTOR_CACHE_TTL_HOURS', 12),
    'feed_token' => env('FORMULARY_FEED_TOKEN', ''),
    'backup_path' => env('FORMULARY_BACKUP_PATH', storage_path('app/backups')),
    'backup_retention_days' => (int) env('FORMULARY_BACKUP_RETENTION_DAYS', 30),
    'review_secret' => env('FORMULARY_REVIEW_SECRET', ''),
    'sheet_webhook_url' => env('FORMULARY_SHEET_WEBHOOK_URL', ''),
    'sheet_webhook_secret' => env('FORMULARY_SHEET_WEBHOOK_SECRET', ''),
];
```

### 5.3 Layanan Sinkronisasi Google Sheets (`app/Services/GoogleSheetSyncService.php`)

Kelas enkapsulasi integrasi webhook Google Apps Script dengan 12 kolom kanonikal:
```php
namespace App\Services;

use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Queries\ProposalItemRecapQuery;
use App\Support\SpreadsheetSanitizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleSheetSyncService
{
    /**
     * Mendorong data usulan baru secara instan ke Google Sheets Webhook (< 2 detik).
     */
    public function pushNewProposal(Proposal $proposal): bool
    {
        $webhookUrl = (string) config('formulary.sheet_webhook_url');
        if ($webhookUrl === '') {
            return false;
        }

        $proposal->loadMissing('items');
        if ($proposal->items->isEmpty()) {
            return false;
        }

        $rows = [];
        foreach ($proposal->items as $index => $item) {
            $rows[] = [
                'no' => $index + 1,
                'proposal_date' => $proposal->proposal_date->format('d/m/Y'),
                'period_year' => $proposal->period_year,
                'doctor_name' => SpreadsheetSanitizer::sanitizeCell($proposal->doctor_name),
                'medicine_name' => SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                'principal_name' => $item->principal_name ? SpreadsheetSanitizer::sanitizeCell($item->principal_name) : '-',
                'clinical_justification' => $item->clinical_justification ? SpreadsheetSanitizer::sanitizeCell($item->clinical_justification) : '-',
                'notes' => $proposal->notes ? SpreadsheetSanitizer::sanitizeCell($proposal->notes) : '-',
                'created_at' => $item->created_at ? $item->created_at->format('d/m/Y H:i:s') : '-',
                'review_status' => $item->review_status ?? 'pending',
                'review_notes' => $item->review_notes ? SpreadsheetSanitizer::sanitizeCell($item->review_notes) : '-',
                'reviewer_name' => $item->reviewer_name ?? '-',
            ];
        }

        try {
            $response = Http::timeout(5)
                ->retry(2, 100, throw: false)
                ->acceptJson()
                ->post($webhookUrl, [
                    'secret' => (string) config('formulary.sheet_webhook_secret'),
                    'action' => 'append_rows',
                    'rows' => $rows,
                ]);

            if (! $response->successful()) {
                Log::warning('Sinkronisasi Google Sheet gagal dengan status: '.$response->status());
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Pengecualian saat menghubungi Google Sheet Webhook: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Sinkronisasi massal seluruh data usulan ke Google Sheets.
     *
     * @return int Jumlah baris yang berhasil dikirim
     */
    public function syncAll(?int $periodYear = null, bool $clearFirst = false): int
    {
        $webhookUrl = (string) config('formulary.sheet_webhook_url');
        if ($webhookUrl === '') {
            return 0;
        }

        if ($clearFirst) {
            Http::timeout(10)->post($webhookUrl, [
                'secret' => (string) config('formulary.sheet_webhook_secret'),
                'action' => 'clear_sheet',
            ]);
        }

        $filters = array_filter(['period_year' => $periodYear]);
        $query = ProposalItemRecapQuery::build($filters);
        $totalSent = 0;
        $counter = 1;

        $query->chunk(200, function ($items) use ($webhookUrl, &$totalSent, &$counter): void {
            $rows = [];
            foreach ($items as $item) {
                $rows[] = [
                    'no' => $counter++,
                    'proposal_date' => \Carbon\Carbon::parse($item->proposal_date)->format('d/m/Y'),
                    'period_year' => $item->period_year,
                    'doctor_name' => SpreadsheetSanitizer::sanitizeCell($item->doctor_name),
                    'medicine_name' => SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                    'principal_name' => $item->principal_name ? SpreadsheetSanitizer::sanitizeCell($item->principal_name) : '-',
                    'clinical_justification' => $item->clinical_justification ? SpreadsheetSanitizer::sanitizeCell($item->clinical_justification) : '-',
                    'notes' => $item->notes ? SpreadsheetSanitizer::sanitizeCell($item->notes) : '-',
                    'created_at' => $item->created_at ? $item->created_at->format('d/m/Y H:i:s') : '-',
                    'review_status' => $item->review_status ?? 'pending',
                    'review_notes' => $item->review_notes ? SpreadsheetSanitizer::sanitizeCell($item->review_notes) : '-',
                    'reviewer_name' => $item->reviewer_name ?? '-',
                ];
            }

            Http::timeout(15)->post($webhookUrl, [
                'secret' => (string) config('formulary.sheet_webhook_secret'),
                'action' => 'append_rows',
                'rows' => $rows,
            ]);

            $totalSent += count($rows);
        });

        return $totalSent;
    }
}
```

### 5.4 Eksekusi Zero-Latency via `defer()` (`ProposalController.php`)

Pada `ProposalController::store()`:
```php
DB::transaction(function () use ($normalizedPayload, $carbonDate, &$proposal): void {
    $proposal = Proposal::create([...]);
    $proposal->items()->createMany($normalizedPayload['items']);
});

if ($proposal) {
    defer(function () use ($proposal): void {
        app(GoogleSheetSyncService::class)->pushNewProposal($proposal);
    });
}
```

### 5.5 Modul Telaah KFT & Otorisasi Sesi Farmasi (`ProposalReviewController.php`)

Controller baru `app/Http/Controllers/ProposalReviewController.php`:
1. **Autentikasi Sesi KFT (`POST /recap/auth`):**
   - Memvalidasi input `secret` terhadap `config('formulary.review_secret')` menggunakan `hash_equals()`.
   - Jika cocok: `session(['kft_authenticated' => true, 'reviewer_name' => $request->input('reviewer_name', 'Tim Farmasi')])`.
   - Mengembalikan respons JSON sukses.
2. **Logout Sesi KFT (`POST /recap/logout`):**
   - `session()->forget(['kft_authenticated', 'reviewer_name'])`.
3. **Pembaruan Status Telaah (`PATCH /proposals/items/{item}/review`):**
   - Memeriksa `session('kft_authenticated') === true`. Jika tidak, kembalikan HTTP 403 Forbidden.
   - Validasi input:
     - `review_status`: `['required', Rule::in(['pending', 'approved', 'rejected', 'substituted'])]`
     - `review_notes`: `['nullable', 'string', 'max:2000']`
   - Update model `ProposalItem`:
     ```php
     $item->update([
         'review_status' => $validated['review_status'],
         'review_notes' => TextNormalizer::collapseWhitespace($validated['review_notes'] ?? null),
         'reviewed_at' => now(),
         'reviewer_name' => session('reviewer_name', 'Tim Farmasi'),
     ]);
     ```
   - Mengembalikan JSON detail item yang terbarui untuk render dinamis di Blade / Alpine.js.

### 5.6 Safeguard Live CSV Feed Anti `#REF!` & Ekspor Excel 12 Kolom (`ProposalExportController.php`)

1. **Pembaruan Live CSV Feed (`/exports/proposals.csv`):**
   - **Default (tanpa parameter `include_review`):** Mengeluarkan persis 8 kolom lama yang identik dengan v1.0.0, menjamin tidak ada konflik *array spill* pada formula `=IMPORTDATA(...)` Google Sheets yang sudah aktif.
   - **Opt-in (`?include_review=1`):** Menghasilkan 12 kolom kanonikal lengkap:
     `[No, Tanggal, Tahun Periode, Nama Dokter, Nama Obat, Nama Principle, Alasan Klinis, Catatan Pengusul, Waktu Input, Status Telaah, Catatan Telaah, Penelaah]`.
2. **Pembaruan Ekspor Excel (`/exports/proposals.xlsx`):**
   - Berkas XLSX adalah berkas statis (bebas risiko formula spill). Otomatis menyertakan 12 kolom kanonikal di atas secara utuh dengan styling header hijau zamrud dan lebar kolom yang disesuaikan.

### 5.7 Template Google Apps Script Resmi (`resources/scripts/google-apps-script-webhook.js`)

Menyediakan file skrip referensi resmi di repositori untuk dipasang tim IT pada Google Spreadsheet:
```javascript
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
```

### 5.8 Artisan Command Rekonsiliasi Massal (`app/Console/Commands/SyncGoogleSheetCommand.php`)

- **Signature:** `formulary:sync-sheets {--year= : Filter tahun periode} {--clear : Bersihkan baris ke-2 ke bawah sebelum menulis ulang}`
- **Deskripsi:** *"Mengirimkan data usulan obat secara massal ke Google Sheets Webhook."*
- **Penanganan Proteksi Duplikasi:**
  - Jika `--clear` tidak disertakan, command meminta konfirmasi interaktif:
    ```php
    if (! $this->option('clear') && ! $this->confirm('Perhatian: Sinkronisasi tanpa --clear akan menambahkan seluruh baris ke bawah lembar kerja. Lanjutkan?', false)) {
        $this->info('Sinkronisasi dibatalkan.');
        return Command::SUCCESS;
    }
    ```

---

## 6. What's Left to the Builder

- Membuat berkas migrasi penambahan kolom telaah pada `proposal_items`.
- Mengimplementasikan `GoogleSheetSyncService` dan penulisan skrip Apps Script di `resources/scripts/`.
- Memasang pemicu `defer()` pada `ProposalController::store()`.
- Mengimplementasikan `ProposalReviewController` beserta rute-rutenya.
- Mengintegrasikan UI modal passcode KFT dan dropdown status telaah inline pada tabel rekapitulasi.
- Mengimplementasikan Artisan command `php artisan formulary:sync-sheets`.
- Menulis unit/feature test komprehensif di Pest PHP:
  - `ProposalReviewTest`: Menguji autentikasi passcode KFT, penolakan akses tanpa passcode, dan update status/catatan telaah.
  - `GoogleSheetSyncServiceTest`: Menguji format payload baris, skenario HTTP mock sukses/gagal, dan verifikasi `defer()` tidak mengganggu store proposal.
  - `ProposalExportReviewCsvTest`: Menguji pemisahan kontrak CSV default 8 kolom vs opt-in `include_review=1`.
  - `SyncGoogleSheetCommandTest`: Menguji eksekusi CLI bulk sync.
- Menjalankan `./vendor/bin/pest` untuk memastikan 100% lulus.
- Menjalankan `./vendor/bin/pint --test` untuk memastikan kebersihan format PHP.
- Menjalankan `npm run build` untuk memverifikasi kompatibilitas aset.
