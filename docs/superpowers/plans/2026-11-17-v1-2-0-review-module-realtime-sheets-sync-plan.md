# FormulariumRS v1.2.0 Implementation Plan: Modul Telaah KFT Farmasi & Sinkronisasi Real-Time Google Sheets

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membangun modul telaah status usulan obat Sidang Komite Farmasi dan Terapi (KFT) dengan otorisasi sesi passcode rahasia dan transparansi klinis publik, safeguard ekspor spreadsheet anti formula spill (8 kolom default vs 12 kolom opt-in), pendorongan usulan baru secara instan (< 2 detik) ke Google Sheets via Laravel 12 `defer()`, serta perintah CLI rekonsiliasi massal `formulary:sync-sheets`.

**Architecture:** 
1. **Basis Data & Model:** Migrasi aditif pada `proposal_items` menambahkan kolom `review_status` (*pending*, *approved*, *rejected*, *substituted*), `review_notes`, `reviewed_at`, dan `reviewer_name` dengan indeks dan query scope di `ProposalItemRecapQuery`.
2. **Real-time Webhook Push:** `GoogleSheetSyncService` memanfaatkan helper Laravel 12 `defer()` di `ProposalController::store()` untuk mendorong 12 kolom kanonikal ke Google Apps Script Webhook secara asynchronous pasca penutupan koneksi HTTP respons (latensi dokter 0 ms).
3. **Otorisasi Sesi Farmasi:** `ProposalReviewController` melindungi mutasi telaah dengan verifikasi passcode `FORMULARY_REVIEW_SECRET` via `hash_equals()` yang disimpan pada sesi browser apoteker (`session('kft_authenticated')`), sementara status telaah tetap terbaca publik untuk transparansi klinis.
4. **Safeguard Spreadsheet:** Mempertahankan kontrak ketat 8 kolom pada CSV live feed default `/exports/proposals.csv` untuk mencegah error formula `#REF!` pada Google Sheets yang sudah aktif, sembari menyediakan parameter opt-in `include_review=1` dan ekspor XLSX 12 kolom ber-style.
5. **CLI Rekonsiliasi Massal:** `SyncGoogleSheetCommand` (`formulary:sync-sheets`) untuk penulisan ulang atau inisialisasi lembar kerja Google Sheets dengan proteksi konfirmasi duplikasi.

**Tech Stack:** PHP `^8.3`, Laravel `^12.0`, SQLite 3 (`WAL`), OpenSpout `^4.32`, Blade Components, Tailwind CSS `^4.0`, Alpine.js `^3.17`, Pest PHP `^3.7`, Laravel Pint `^1.24`.

**Spec:** [docs/superpowers/specs/2026-11-16-v1-2-0-review-module-realtime-sheets-sync-design.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/docs/superpowers/specs/2026-11-16-v1-2-0-review-module-realtime-sheets-sync-design.md)

## Global Constraints

- Wajib mematuhi pedoman teknis [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md): `declare(strict_types=1);` di setiap berkas PHP baru, tanpa generic repository pattern, dan tanpa library eksternal baru.
- Dilarang memasang authentication guard atau PIN pada form pengajuan dokter (`GET /` dan `POST /proposals`); form dokter wajib tetap 100% *zero-friction*.
- Dilarang mengubah urutan atau struktur default 8 kolom pada `/exports/proposals.csv` tanpa parameter opt-in `include_review=1`.
- Dilarang menggagalkan transaksi penyimpanan dokter di database jika webhook Google Sheets mengalami timeout atau koneksi internet Google terputus.
- Seluruh perbandingan token/secret wajib menggunakan fungsi waktu-konstan `hash_equals()`.
- Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS (minimal 56 tests baseline).
- Standarisasi formatting Laravel Pint (`./vendor/bin/pint --test`) wajib bersih tanpa error.

## Review Focus

1. **Zero-Latency Doctor Experience:** Pengiriman webhook Google Sheets pada `ProposalController::store()` wajib dieksekusi di dalam `defer()`, sehingga kegagalan koneksi atau latensi jaringan Google tidak memperlambat respons atau membatalkan transaksi penyimpanan usulan dokter. *(Diuji di Task 2).*
2. **Keamanan Otorisasi Passcode KFT:** Endpoint `PATCH /proposals/items/{item}/review` wajib menolak mutasi dengan kode HTTP 403 Forbidden jika sesi `kft_authenticated` tidak aktif atau secret yang dimasukkan salah. *(Diuji di Task 3).*
3. **Imutabilitas Kontrak CSV Default (Anti-#REF!):** Panggilan `/exports/proposals.csv` tanpa parameter opt-in wajib tetap menghasilkan persis 8 kolom lama. Parameter `include_review=1` menghasilkan 12 kolom kanonikal. *(Diuji di Task 4).*
4. **Proteksi Duplikasi CLI `sync-sheets`:** Menjalankan perintah `formulary:sync-sheets` tanpa opsi `--clear` wajib meminta konfirmasi interaktif sebelum mengeksekusi penambahan baris. *(Diuji di Task 5).*
5. **Transparansi Status Klinis & Sanitasi XSS/Formula:** Teks catatan telaah `review_notes` dan status `review_status` ditampilkan aman di antarmuka Blade tanpa risiko XSS dan ter-escape aman saat diekspor ke CSV/XLSX. *(Diuji di Task 4 dan Task 6).*

---

### Task 1: Database Migration, Model Attributes & Query Extension

**Files:**
- Create: `database/migrations/2026_11_17_000001_add_review_columns_to_proposal_items_table.php`
- Modify: `app/Models/ProposalItem.php`
- Modify: `config/formulary.php`
- Modify: `app/Queries/ProposalItemRecapQuery.php`
- Test: `tests/Feature/DatabaseSchemaAndWalTest.php`

**Interfaces:**
- Consumes: Database schema `proposal_items`
- Produces:
  - Columns on `proposal_items`: `review_status` (string 32, default `'pending'`, index), `review_notes` (text, nullable), `reviewed_at` (datetime, nullable), `reviewer_name` (string, nullable).
  - Config keys: `formulary.review_secret`, `formulary.sheet_webhook_url`, `formulary.sheet_webhook_secret`.
  - `ProposalItemRecapQuery::build()` selects review fields and supports `review_status` filter & sorting.

- [ ] **Step 1: Write the failing test**

Tambahkan pengujian skema kolom telaah di `tests/Feature/DatabaseSchemaAndWalTest.php`:
```php
it('has review columns on proposal_items table with default pending status', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Fatimah Sp.A',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);

    $item = $proposal->items()->create([
        'medicine_name' => 'Amoxicillin Sirup',
    ]);

    expect($item->fresh()->review_status)->toBe('pending')
        ->and($item->fresh()->review_notes)->toBeNull()
        ->and($item->fresh()->reviewed_at)->toBeNull()
        ->and($item->fresh()->reviewer_name)->toBeNull();
});
```

- [ ] **Step 2: Run test to confirm it fails**
Jalankan `./vendor/bin/pest tests/Feature/DatabaseSchemaAndWalTest.php` dan pastikan gagal karena kolom belum ada.

- [ ] **Step 3: Implement migration, model, query, and configuration**

1. Buat berkas migrasi `database/migrations/2026_11_17_000001_add_review_columns_to_proposal_items_table.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
};
```

2. Perbarui `app/Models/ProposalItem.php`:
Tambahkan `$fillable` baru (`review_status`, `review_notes`, `reviewed_at`, `reviewer_name`) dan tambahkan method `casts(): array` dengan `'reviewed_at' => 'datetime'`.

3. Perbarui `config/formulary.php`:
Tambahkan:
```php
    'review_secret' => env('FORMULARY_REVIEW_SECRET', ''),
    'sheet_webhook_url' => env('FORMULARY_SHEET_WEBHOOK_URL', ''),
    'sheet_webhook_secret' => env('FORMULARY_SHEET_WEBHOOK_SECRET', ''),
```

4. Perbarui `app/Queries/ProposalItemRecapQuery.php`:
- Tambahkan kolom telaah ke dalam array `select`:
  `proposal_items.review_status`, `proposal_items.review_notes`, `proposal_items.reviewed_at`, `proposal_items.reviewer_name`.
- Tambahkan filter `review_status`:
  ```php
  if (! empty($filters['review_status'])) {
      $query->where('proposal_items.review_status', (string) $filters['review_status']);
  }
  ```
- Tambahkan `'status' => 'proposal_items.review_status'` pada `SORT_MAP`.

- [ ] **Step 4: Run migration and verify test passes**
Jalankan `php artisan migrate` dan `./vendor/bin/pest tests/Feature/DatabaseSchemaAndWalTest.php`.

- [ ] **Step 5: Run Pint and commit**
```bash
./vendor/bin/pint
git add database/migrations/ app/Models/ProposalItem.php config/formulary.php app/Queries/ProposalItemRecapQuery.php tests/Feature/DatabaseSchemaAndWalTest.php
git commit -m "feat(db): add review workflow columns to proposal_items and recap query"
```

---

### Task 2: Google Sheets Real-Time Sync Service, Apps Script Webhook Template & Zero-Latency `defer()` Trigger

**Files:**
- Create: `resources/scripts/google-apps-script-webhook.js`
- Create: `app/Services/GoogleSheetSyncService.php`
- Modify: `app/Http/Controllers/ProposalController.php:70-85`
- Create: `tests/Feature/GoogleSheetSyncServiceTest.php`

**Interfaces:**
- Consumes: `Proposal`, `ProposalItem`, `config('formulary.sheet_webhook_url')`, `config('formulary.sheet_webhook_secret')`
- Produces:
  - `GoogleSheetSyncService::pushNewProposal(Proposal $proposal): bool`
  - `GoogleSheetSyncService::syncAll(?int $periodYear = null, bool $clearFirst = false): int`
  - Zero-latency asynchronous dispatch via `defer(...)` in `ProposalController::store()`

- [ ] **Step 1: Create Google Apps Script Webhook Template**
Buat berkas `resources/scripts/google-apps-script-webhook.js` berisi kode resmi Google Apps Script Webhook per spec (bagian 5.7) yang menangani `append_rows` dan `clear_sheet` (membersihkan baris ke-2 ke bawah dengan header baris ke-1 tetap utuh).

- [ ] **Step 2: Write the failing tests**
Buat `tests/Feature/GoogleSheetSyncServiceTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Services\GoogleSheetSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('pushes new proposal rows with 12 canonical columns to webhook', function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');

    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'success', 'count' => 1], 200),
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Agus Sp.B',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
        'notes' => 'Catatan dokter',
    ]);
    $item = $proposal->items()->create([
        'medicine_name' => 'Ceftriaxone 1g Inj',
        'principal_name' => 'Bernofarm',
        'clinical_justification' => 'Antibiotik profilaksis',
    ]);

    $service = app(GoogleSheetSyncService::class);
    $result = $service->pushNewProposal($proposal);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->data();
        return $body['secret'] === 'secret123'
            && $body['action'] === 'append_rows'
            && count($body['rows']) === 1
            && $body['rows'][0]['doctor_name'] === 'dr. Agus Sp.B'
            && $body['rows'][0]['medicine_name'] === 'Ceftriaxone 1g Inj'
            && $body['rows'][0]['review_status'] === 'pending';
    });
});

it('gracefully handles http failures and empty webhook url without throwing exceptions', function (): void {
    config()->set('formulary.sheet_webhook_url', '');

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Agus Sp.B',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);

    $service = app(GoogleSheetSyncService::class);
    expect($service->pushNewProposal($proposal))->toBeFalse();
});
```

- [ ] **Step 3: Implement `GoogleSheetSyncService`**
Buat `app/Services/GoogleSheetSyncService.php`:
- Implementasikan `pushNewProposal(Proposal $proposal): bool`
- Implementasikan `syncAll(?int $periodYear = null, bool $clearFirst = false): int` dengan sanitasi `SpreadsheetSanitizer::sanitizeCell()`, pemisahan chunk 200 baris, dan penanganan aksi `clear_sheet` jika `$clearFirst` bernilai `true`.

- [ ] **Step 4: Wire `defer()` in `ProposalController::store()`**
Perbarui `app/Http/Controllers/ProposalController.php`:
Tepat setelah `DB::transaction(...)` berhasil dan `$proposal` terbuat:
```php
if ($proposal) {
    defer(function () use ($proposal): void {
        app(GoogleSheetSyncService::class)->pushNewProposal($proposal);
    });
}
```

- [ ] **Step 5: Run tests and verify**
Jalankan `./vendor/bin/pest tests/Feature/GoogleSheetSyncServiceTest.php tests/Feature/ProposalSubmissionTest.php`.

- [ ] **Step 6: Run Pint and commit**
```bash
./vendor/bin/pint
git add resources/scripts/ app/Services/GoogleSheetSyncService.php app/Http/Controllers/ProposalController.php tests/Feature/GoogleSheetSyncServiceTest.php
git commit -m "feat(sync): implement GoogleSheetSyncService with defer zero-latency push"
```

---

### Task 3: KFT Review Workflow Controller & Passcode Session Authentication

**Files:**
- Create: `app/Http/Controllers/ProposalReviewController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/ProposalReviewTest.php`

**Interfaces:**
- Consumes: `Request`, `ProposalItem`, `config('formulary.review_secret')`
- Produces:
  - `POST /recap/auth` -> JSON `{ success: true, reviewer_name: '...' }`
  - `POST /recap/logout` -> JSON `{ success: true }`
  - `PATCH /proposals/items/{item}/review` -> JSON `{ success: true, item: [...] }` (403 if unauthenticated)

- [ ] **Step 1: Write the failing tests**
Buat `tests/Feature/ProposalReviewTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('formulary.review_secret', 'kft-secret-2026');
});

it('authenticates KFT session with valid passcode and rejects invalid passcode', function (): void {
    $response = $this->postJson(route('recap.auth'), [
        'secret' => 'wrong-passcode',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['secret']);

    $responseSuccess = $this->postJson(route('recap.auth'), [
        'secret' => 'kft-secret-2026',
        'reviewer_name' => 'apt. Siti Rahma, S.Farm',
    ]);
    $responseSuccess->assertOk()
        ->assertJson(['success' => true]);

    $this->assertEquals(true, session('kft_authenticated'));
    $this->assertEquals('apt. Siti Rahma, S.Farm', session('reviewer_name'));
});

it('denies unauthenticated review update requests with 403 forbidden', function (): void {
    $proposal = Proposal::create(['doctor_name' => 'dr. Budi', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $item = $proposal->items()->create(['medicine_name' => 'Paracetamol 500mg']);

    $response = $this->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'approved',
    ]);
    $response->assertForbidden();
});

it('updates review status and notes when authenticated in KFT session', function (): void {
    $proposal = Proposal::create(['doctor_name' => 'dr. Budi', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $item = $proposal->items()->create(['medicine_name' => 'Paracetamol 500mg']);

    $response = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'apt. Siti Rahma',
    ])->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'approved',
        'review_notes' => 'Disetujui masuk Formularium 2026',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'item' => [
                'id' => $item->id,
                'review_status' => 'approved',
                'review_notes' => 'Disetujui masuk Formularium 2026',
                'reviewer_name' => 'apt. Siti Rahma',
            ],
        ]);

    expect($item->fresh()->review_status)->toBe('approved')
        ->and($item->fresh()->reviewed_at)->not->toBeNull()
        ->and($item->fresh()->reviewer_name)->toBe('apt. Siti Rahma');
});

it('logs out KFT session and clears credentials from session', function (): void {
    $response = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'apt. Siti Rahma',
    ])->postJson(route('recap.logout'));

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(session('kft_authenticated'))->toBeNull()
        ->and(session('reviewer_name'))->toBeNull();
});
```

- [ ] **Step 2: Run test to confirm it fails**
Jalankan `./vendor/bin/pest tests/Feature/ProposalReviewTest.php`.

- [ ] **Step 3: Implement `ProposalReviewController` and routes**
1. Buat `app/Http/Controllers/ProposalReviewController.php`:
- Method `auth(Request $request): JsonResponse`
  - Validasi secret menggunakan `hash_equals(config('formulary.review_secret'), (string) $request->input('secret'))`.
  - Simpan sesi `kft_authenticated = true` dan `reviewer_name = $request->input('reviewer_name', 'Tim Farmasi')`.
- Method `logout(Request $request): JsonResponse`
  - Lupakan sesi `kft_authenticated` dan `reviewer_name`.
- Method `update(Request $request, ProposalItem $item): JsonResponse`
  - Periksa `session('kft_authenticated') === true`, jika tidak `abort(403)`.
  - Validasi input `review_status` (`Rule::in(['pending', 'approved', 'rejected', 'substituted'])`) dan `review_notes` (`nullable|string|max:2000`).
  - Update `$item` dengan sanitasi `TextNormalizer::collapseWhitespace()`, set `reviewed_at = now()`, dan `reviewer_name = session('reviewer_name', 'Tim Farmasi')`.

2. Daftarkan rute di `routes/web.php`:
```php
Route::post('/recap/auth', [ProposalReviewController::class, 'auth'])->name('recap.auth');
Route::post('/recap/logout', [ProposalReviewController::class, 'logout'])->name('recap.logout');
Route::patch('/proposals/items/{item}/review', [ProposalReviewController::class, 'update'])->name('proposals.items.review');
```

- [ ] **Step 4: Run tests and verify**
Jalankan `./vendor/bin/pest tests/Feature/ProposalReviewTest.php`.

- [ ] **Step 5: Run Pint and commit**
```bash
./vendor/bin/pint
git add app/Http/Controllers/ProposalReviewController.php routes/web.php tests/Feature/ProposalReviewTest.php
git commit -m "feat(review): implement ProposalReviewController with passcode session auth"
```

---

### Task 4: Exports Safeguard (8-Column Backward-Compatible CSV, 12-Column Opt-in CSV, and 12-Column XLSX)

**Files:**
- Modify: `app/Http/Controllers/ProposalExportController.php`
- Modify: `app/Services/ProposalExportService.php`
- Create: `tests/Feature/ProposalExportReviewCsvTest.php`
- Modify: `tests/Feature/ProposalExportTest.php`

**Interfaces:**
- Consumes: `ProposalItemRecapQuery`, `SpreadsheetSanitizer`
- Produces:
  - Default `GET /exports/proposals.csv` -> exactly 8 columns (No, Tanggal, Tahun Periode, Nama Dokter, Nama Obat, Nama Principle, Alasan Klinis, Catatan Pengusul).
  - Opt-in `GET /exports/proposals.csv?include_review=1` -> 12 canonical columns including review status, review notes, and reviewer name.
  - `GET /exports/proposals.xlsx` -> 12 canonical columns with Emerald styling.

- [ ] **Step 1: Write the failing tests**
Buat `tests/Feature/ProposalExportReviewCsvTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('formulary.feed_token', 'test-valid-token');
});

it('exports default 8 columns when include_review is omitted for backward compatibility', function (): void {
    $proposal = Proposal::create(['doctor_name' => 'dr. Rudi Sp.PD', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $proposal->items()->create(['medicine_name' => 'Metformin 500mg', 'review_status' => 'approved', 'review_notes' => 'Sesuai Fornas']);

    $response = $this->get('/exports/proposals.csv?token=test-valid-token');
    $response->assertOk();

    $content = $response->streamedContent();
    $lines = explode("\n", trim($content));
    $header = str_getcsv($lines[0]);

    expect(count($header))->toBe(8)
        ->and($header)->toEqual([
            'No', 'Tanggal', 'Tahun Periode', 'Nama Dokter', 'Nama Obat', 'Nama Principle', 'Alasan Klinis', 'Catatan Pengusul'
        ]);
});

it('exports 12 canonical columns when include_review=1 is requested', function (): void {
    $proposal = Proposal::create(['doctor_name' => 'dr. Rudi Sp.PD', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $proposal->items()->create([
        'medicine_name' => 'Metformin 500mg',
        'principal_name' => 'Dexa',
        'clinical_justification' => 'Lini pertama DM',
        'review_status' => 'approved',
        'review_notes' => 'Sesuai Fornas',
        'reviewer_name' => 'apt. Siti',
    ]);

    $response = $this->get('/exports/proposals.csv?token=test-valid-token&include_review=1');
    $response->assertOk();

    $content = $response->streamedContent();
    $lines = explode("\n", trim($content));
    $header = str_getcsv($lines[0]);
    $dataRow = str_getcsv($lines[1]);

    expect(count($header))->toBe(12)
        ->and($header[9])->toBe('Status Telaah')
        ->and($header[10])->toBe('Catatan Telaah')
        ->and($header[11])->toBe('Penelaah')
        ->and($dataRow[9])->toBe('approved')
        ->and($dataRow[10])->toBe('Sesuai Fornas')
        ->and($dataRow[11])->toBe('apt. Siti');
});
```

- [ ] **Step 2: Run test to confirm it fails**
Jalankan `./vendor/bin/pest tests/Feature/ProposalExportReviewCsvTest.php`.

- [ ] **Step 3: Implement export controllers and services**
1. Perbarui `app/Http/Controllers/ProposalExportController.php`:
- Di method `csv()`: baca `$includeReview = $request->boolean('include_review');`
- Jika `$includeReview === true`, tulis header 12 kolom dan tulis 12 nilai data (termasuk status telaah, catatan telaah, reviewer name). Jika `false`, pertahankan 8 kolom lama.
2. Perbarui `app/Services/ProposalExportService.php`:
- Di method `generateXlsx()`: tambahkan lebar kolom untuk kolom 9, 10, 11, 12.
- Tambahkan header 12 kolom dan tulis nilai baris lengkap 12 kolom ke XLSX.

- [ ] **Step 4: Run tests and verify**
Jalankan `./vendor/bin/pest tests/Feature/ProposalExportReviewCsvTest.php tests/Feature/ProposalExportTest.php`.

- [ ] **Step 5: Run Pint and commit**
```bash
./vendor/bin/pint
git add app/Http/Controllers/ProposalExportController.php app/Services/ProposalExportService.php tests/Feature/ProposalExportReviewCsvTest.php
git commit -m "feat(exports): safeguard 8-column default CSV and implement 12-column opt-in and XLSX"
```

---

### Task 5: Artisan Reconciliation Command `formulary:sync-sheets`

**Files:**
- Create: `app/Console/Commands/SyncGoogleSheetCommand.php`
- Create: `tests/Feature/SyncGoogleSheetCommandTest.php`

**Interfaces:**
- Consumes: `GoogleSheetSyncService`
- Produces: Artisan command `formulary:sync-sheets {--year= : Filter tahun periode} {--clear : Bersihkan baris ke-2 ke bawah sebelum menulis ulang}`

- [ ] **Step 1: Write the failing tests**
Buat `tests/Feature/SyncGoogleSheetCommandTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');
});

it('aborts bulk sync when confirmation is rejected without clear option', function (): void {
    $this->artisan('formulary:sync-sheets')
        ->expectsConfirmation('Perhatian: Sinkronisasi tanpa --clear akan menambahkan seluruh baris ke bawah lembar kerja. Lanjutkan?', 'no')
        ->expectsOutputToContain('Sinkronisasi dibatalkan.')
        ->assertSuccessful();
});

it('synchronizes all proposals to google sheets with clear option', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'success'], 200),
    ]);

    $proposal = Proposal::create(['doctor_name' => 'dr. Hasan', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $proposal->items()->create(['medicine_name' => 'Amoxicillin 500mg']);

    $this->artisan('formulary:sync-sheets --clear')
        ->expectsOutputToContain('Sinkronisasi massal ke Google Sheets berhasil')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->data()['action'] === 'clear_sheet';
    });
    Http::assertSent(function ($request) {
        return $request->data()['action'] === 'append_rows';
    });
});
```

- [ ] **Step 2: Run test to confirm it fails**
Jalankan `./vendor/bin/pest tests/Feature/SyncGoogleSheetCommandTest.php`.

- [ ] **Step 3: Implement `SyncGoogleSheetCommand`**
Buat `app/Console/Commands/SyncGoogleSheetCommand.php`:
- Signature: `formulary:sync-sheets {--year= : Filter tahun periode} {--clear : Bersihkan baris ke-2 ke bawah sebelum menulis ulang}`
- Deskripsi: `Mengirimkan data usulan obat secara massal ke Google Sheets Webhook.`
- Handle:
  - Cek konfigurasi `sheet_webhook_url`. Jika kosong, beri pesan error dan keluar.
  - Cek opsi `--clear`. Jika tidak disertakan dan proses interaktif, minta konfirmasi dengan pesan peringatan duplikasi.
  - Panggil `GoogleSheetSyncService::syncAll($year, $clearFirst)`.
  - Tampilkan ringkasan total baris yang tersinkronisasi.

- [ ] **Step 4: Run tests and verify**
Jalankan `./vendor/bin/pest tests/Feature/SyncGoogleSheetCommandTest.php`.

- [ ] **Step 5: Run Pint and commit**
```bash
./vendor/bin/pint
git add app/Console/Commands/SyncGoogleSheetCommand.php tests/Feature/SyncGoogleSheetCommandTest.php
git commit -m "feat(cli): add formulary:sync-sheets bulk reconciliation command"
```

---

### Task 6: Interactive Recap UI (KFT Mode Passcode Modal, Status Filter, Transparent Badges, and Inline Review Editor)

**Files:**
- Modify: `resources/views/proposals/index.blade.php`
- Modify: `app/Http/Controllers/ProposalRecapController.php`
- Create: `tests/Feature/ProposalRecapReviewUiTest.php`

**Interfaces:**
- Consumes: Sesi `kft_authenticated`, kolom telaah `ProposalItem`, rute `recap.auth`, `recap.logout`, `proposals.items.review`
- Produces:
  - Header KFT Mode button & status indicator
  - Passcode Modal (Alpine.js)
  - Review Status badges (*Menunggu*, *Disetujui*, *Ditolak*, *Substitusi*)
  - Inline "Edit Telaah" modal / popover with optimistic dynamic feedback
  - Filter dropdown "Semua Status Telaah" di baris filter rekapitulasi

- [ ] **Step 1: Write the failing tests**
Buat `tests/Feature/ProposalRecapReviewUiTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders review status badges and clinical transparency for all users', function (): void {
    $proposal = Proposal::create(['doctor_name' => 'dr. Hasan Sp.PD', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $item = $proposal->items()->create([
        'medicine_name' => 'Cefixime 100mg',
        'review_status' => 'approved',
        'review_notes' => 'Disetujui untuk rawat jalan',
    ]);

    $response = $this->get(route('proposals.index'));
    $response->assertOk()
        ->assertSee('Disetujui')
        ->assertSee('Disetujui untuk rawat jalan')
        ->assertSee('Mode Telaah KFT');
});

it('renders active KFT mode badge and reviewer name when session is authenticated', function (): void {
    $response = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'apt. Siti Rahma',
    ])->get(route('proposals.index'));

    $response->assertOk()
        ->assertSee('Mode KFT Aktif')
        ->assertSee('apt. Siti Rahma')
        ->assertSee('Keluar Mode KFT');
});

it('filters recap items by review_status', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. A', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Obat Disetujui', 'review_status' => 'approved']);

    $p2 = Proposal::create(['doctor_name' => 'dr. B', 'proposal_date' => '2026-11-17', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Obat Menunggu', 'review_status' => 'pending']);

    $response = $this->get(route('proposals.index', ['review_status' => 'approved']));
    $response->assertOk()
        ->assertSee('Obat Disetujui')
        ->assertDontSee('Obat Menunggu');
});
```

- [ ] **Step 2: Run test to confirm it fails**
Jalankan `./vendor/bin/pest tests/Feature/ProposalRecapReviewUiTest.php`.

- [ ] **Step 3: Update `ProposalRecapController.php` and `resources/views/proposals/index.blade.php`**
1. Di `ProposalRecapController.php`: teruskan parameter filter `review_status` ke dalam `ProposalItemRecapQuery::build()` dan kirim status review list ke view.
2. Di `resources/views/proposals/index.blade.php`:
- Tambahkan status review filter di form pencarian.
- Tambahkan tombol KFT Mode & Modal Masuk KFT (Alpine.js dengan input passcode secret dan nama penelaah).
- Tambahkan kolom "Status Telaah" & "Catatan KFT" pada tabel rekapitulasi dengan styling badge:
  - *pending*: `bg-amber-50 text-amber-800 border-amber-200`
  - *approved*: `bg-emerald-50 text-emerald-800 border-emerald-200`
  - *rejected*: `bg-rose-50 text-rose-800 border-rose-200`
  - *substituted*: `bg-sky-50 text-sky-800 border-sky-200`
- Ketika `session('kft_authenticated')` bernilai true, tampilkan tombol "Edit Telaah" pada setiap baris untuk membuka modal/drawer cepat update status telaah dan catatan pertimbangan KFT via fetch/AJAX.
- Tambahkan panduan URL opt-in `&include_review=1` pada modal Hubungkan Live Spreadsheet.

- [ ] **Step 4: Run tests and verify**
Jalankan `./vendor/bin/pest tests/Feature/ProposalRecapReviewUiTest.php tests/Feature/ProposalRecapFilterTest.php`.

- [ ] **Step 5: Run Pint and commit**
```bash
./vendor/bin/pint
git add resources/views/proposals/index.blade.php app/Http/Controllers/ProposalRecapController.php tests/Feature/ProposalRecapReviewUiTest.php
git commit -m "feat(ui): add KFT review mode, status badges, and inline review editor"
```

---

## Plan Verification & Quality Gate

Sebelum pembuatan tag rilis `v1.2.0`:
1. Jalankan seluruh test suite: `./vendor/bin/pest` (wajib 100% PASS, minimal 62 tests).
2. Jalankan Laravel Pint dry-run: `./vendor/bin/pint --test` (wajib 0 violations).
3. Jalankan kompilasi aset produksi: `npm run build` (wajib berhasil tanpa error).
4. Verifikasi `git status` bersih.
5. Pembuatan git tag annotated `v1.2.0`:
   ```bash
   git tag -a v1.2.0 -m "Release v1.2.0: Review KFT Farmasi & Real-time Google Sheets Sync"
   ```
