# FormulariumRS v1.0.2 Implementation Plan: Interactive Ordering Rekapitulasi & Data Hardening

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghadirkan fitur pengurutan tabel rekapitulasi multi-kolom interaktif (*interactive column ordering* ASC/DESC) yang terlindungi daftar putih (*whitelist guard*), indeks komposit basis data, penguatan sanitasi formula spreadsheet OWASP, normalisasi awalan badan usaha (`PT`, `CV`), serta Artisan command rekonsiliasi data historis.

**Architecture:** Pengurutan dieksekusi di level basis data melalui `ProposalItemRecapQuery` menggunakan pemetaan daftar putih (`SORT_MAP`) dan *tie-breaker* `proposal_items.id` untuk stabilitas paginasi SQLite. Header tabel menggunakan komponen Blade reusable `<x-table-sort-header>` dengan preservasi query filter aktif dan indikator visual arah (↑, ↓, ⇅). Migrasi aditif menambahkan indeks pada `proposals.doctor_name` dan composite index `['period_year', 'proposal_date']`. Penguatan utilitas pada `SpreadsheetSanitizer` (deteksi leading whitespace dan simbol `|`, `%`) serta `TextNormalizer` (stripping awalan `PT/CV` dan alias kamus `'bernofarm' => 'Berno'`).

**Tech Stack:** PHP `^8.3`, Laravel `^12.0`, SQLite 3 (`WAL`), Blade Components, Tailwind CSS `^4.0`, OpenSpout `^4.32`, Pest PHP `^3.7`, Laravel Pint `^1.24`.

**Spec:** [docs/superpowers/specs/2026-11-15-v1-0-2-recap-interactive-ordering-hardening-design.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/docs/superpowers/specs/2026-11-15-v1-0-2-recap-interactive-ordering-hardening-design.md)

## Global Constraints

- Wajib mematuhi pedoman teknis [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md): `declare(strict_types=1);` di setiap berkas PHP baru/dimodifikasi, tanpa generic repository pattern, dan tanpa dependensi AJAX/Livewire eksternal.
- Migrasi database bersifat aditif (`Schema::table`) tanpa mengubah atau me-drop tabel/kolom yang sedang berjalan.
- Rute live CSV feed (`/exports/proposals.csv`) yang digunakan formula `=IMPORTDATA()` Google Sheets tetap dikunci secara mutlak pada urutan kronologis default agar rumus spreadsheet pihak ketiga tidak bergeser.
- Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS (minimal 30 tests baseline).
- Standarisasi formatting Laravel Pint (`./vendor/bin/pint --test`) wajib bersih tanpa error.
- Kompilasi aset Vite (`npm run build`) wajib berhasil tanpa warning.

## Review Focus

1. **SQL Injection via Parameter `sort_by`:** Parameter acak atau berbahaya dari URL (seperti `sort_by=id;DROP TABLE proposals;--` atau subquery) wajib ditolak oleh `SORT_MAP` whitelist dan otomatis jatuh ke urutan default kronologis. *(Diuji di Task 4).*
2. **Formula Injection melalui Spasi Tersembunyi (*Leading Whitespace*) & Karakter OWASP Tambahan:** Nilai sel seperti `"   =SUM(1,2)"`, `"|cmd"`, atau `"%100"` wajib diprefiks tanda kutip `'` oleh `SpreadsheetSanitizer::sanitizeCell()`. *(Diuji di Task 2).*
3. **Purity Mode `--dry-run` pada Command Normalisasi:** Menjalankan `php artisan formulary:normalize-principals --dry-run` wajib murni menampilkan tabel pratinjau dan tidak melakukan mutasi apa pun pada baris database. *(Diuji di Task 3).*
4. **Preservasi Live CSV Feed Immobility:** Rute `/exports/proposals.csv` wajib mengabaikan parameter sorting `sort_by` / `sort_dir` sehingga spreadsheet Google Sheets pihak ketiga tidak berantakan. *(Diuji di Task 4).*
5. **Deterministic Pagination Tie-Breaker:** Pengurutan kolom yang memiliki nilai kembar (misal nama dokter yang sama) wajib memiliki tie-breaker `proposal_items.id` agar baris data tidak berpindah-pindah antar halaman paginasi. *(Diuji di Task 4).*

---

### Task 1: Database Migration for Composite and Doctor Indexes

**Files:**
- Create: `database/migrations/2026_11_17_000001_add_indexes_to_proposals_table.php`
- Modify: `tests/Feature/DatabaseSchemaAndWalTest.php`

**Interfaces:**
- Consumes: Skema tabel `proposals`
- Produces: Indeks basis data pada kolom `proposals.doctor_name` dan indeks komposit `['period_year', 'proposal_date']`.

- [ ] **Step 1: Write the failing test**

Tambahkan pengujian di `tests/Feature/DatabaseSchemaAndWalTest.php`:
```php
it('has indexes on proposals table for doctor_name and composite period_year proposal_date', function (): void {
    $this->artisan('migrate')->assertSuccessful();

    $indices = collect(DB::select("PRAGMA index_list('proposals')"))->pluck('name');

    expect($indices->some(fn ($name) => str_contains((string) $name, 'doctor_name')))->toBeTrue()
        ->and($indices->some(fn ($name) => str_contains((string) $name, 'period_year')))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/DatabaseSchemaAndWalTest.php`
Expected: FAIL dengan kegagalan assertion bahwa indeks `doctor_name` tidak ditemukan.

- [ ] **Step 3: Implement database migration**

Buat berkas `database/migrations/2026_11_17_000001_add_indexes_to_proposals_table.php`:
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
        Schema::table('proposals', function (Blueprint $table): void {
            $table->index('doctor_name');
            $table->index(['period_year', 'proposal_date']);
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropIndex(['doctor_name']);
            $table->dropIndex(['period_year', 'proposal_date']);
        });
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/DatabaseSchemaAndWalTest.php`
Expected: PASS (seluruh 3 test di `DatabaseSchemaAndWalTest` lolos).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_11_17_000001_add_indexes_to_proposals_table.php tests/Feature/DatabaseSchemaAndWalTest.php
git commit -m "feat(db): add indexes on proposals doctor_name and composite period_year proposal_date"
```

---

### Task 2: Hardening OWASP CSV Sanitizer & Leading Whitespace Handling

**Files:**
- Modify: `app/Support/SpreadsheetSanitizer.php`
- Modify: `tests/Unit/CsvImportNormalizationTest.php`

**Interfaces:**
- Consumes: String nilai sel mentah
- Produces: `SpreadsheetSanitizer::sanitizeCell(?string $value): string` yang menangani leading whitespace serta karakter OWASP berbahaya (`=`, `+`, `-`, `@`, `\t`, `\r`, `|`, `%`).

- [ ] **Step 1: Write the failing test**

Tambahkan assertion pada `tests/Unit/CsvImportNormalizationTest.php`:
```php
it('escapes spreadsheet formula injection prefixes with leading whitespace and dangerous owasp characters', function (): void {
    expect(SpreadsheetSanitizer::sanitizeCell('   =SUM(A1:A10)'))->toBe("'   =SUM(A1:A10)")
        ->and(SpreadsheetSanitizer::sanitizeCell('  +cmd|'))->toBe("'  +cmd|")
        ->and(SpreadsheetSanitizer::sanitizeCell('|powershell'))->toBe("'|powershell")
        ->and(SpreadsheetSanitizer::sanitizeCell('%COMSPEC%'))->toBe("'%COMSPEC%")
        ->and(SpreadsheetSanitizer::sanitizeCell("\t=1+1"))->toBe("'\t=1+1")
        ->and(SpreadsheetSanitizer::sanitizeCell('Normal Text'))->toBe('Normal Text');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/CsvImportNormalizationTest.php`
Expected: FAIL dengan kegagalan assertion bahwa `'   =SUM(A1:A10)'` tidak diprefiks `'`.

- [ ] **Step 3: Implement updated `SpreadsheetSanitizer`**

Perbarui `app/Support/SpreadsheetSanitizer.php`:
```php
<?php

declare(strict_types=1);

namespace App\Support;

final class SpreadsheetSanitizer
{
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r", '|', '%'];

    public static function sanitizeCell(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $trimmed = ltrim($value);
        if ($trimmed === '') {
            return $value;
        }

        $firstChar = mb_substr($trimmed, 0, 1);
        if (in_array($firstChar, self::DANGEROUS_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/CsvImportNormalizationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/SpreadsheetSanitizer.php tests/Unit/CsvImportNormalizationTest.php
git commit -m "feat(security): harden spreadsheet formula injection sanitizer with leading whitespace and owasp chars"
```

---

### Task 3: Normalisasi Awalan Badan Usaha & Kamus Pabrikan

**Files:**
- Modify: `app/Support/TextNormalizer.php`
- Modify: `tests/Unit/CsvImportNormalizationTest.php`

**Interfaces:**
- Consumes: String nama pabrikan mentah
- Produces: `TextNormalizer::normalizePrincipal(?string $value): ?string` yang membersihkan awalan `PT`, `CV`, `UD`, `PD`, `NV` dan memetakan alias `'bernofarm' => 'Berno'`.

- [ ] **Step 1: Write the failing test**

Tambahkan assertion di `tests/Unit/CsvImportNormalizationTest.php`:
```php
it('strips business entity prefixes like pt and cv and resolves bernofarm alias', function (): void {
    expect(TextNormalizer::normalizePrincipal('PT. Novell Pharma'))->toBe('Novell')
        ->and(TextNormalizer::normalizePrincipal('CV Sanbe Farma'))->toBe('Sanbe')
        ->and(TextNormalizer::normalizePrincipal('PT. Bernofarm'))->toBe('Berno')
        ->and(TextNormalizer::normalizePrincipal('UD. Kimia Farma'))->toBe('Kimia Farma')
        ->and(TextNormalizer::normalizePrincipal('PT Kalbe Farma'))->toBe('Kalbe Farma');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/CsvImportNormalizationTest.php`
Expected: FAIL karena awalan `PT.` belum dihilangkan.

- [ ] **Step 3: Implement prefix stripping in `app/Support/TextNormalizer.php`**

Mutakhirkan `app/Support/TextNormalizer.php`:
- Tambahkan `'bernofarm' => 'Berno'` ke `PRINCIPAL_ALIASES`.
- Pada `normalizePrincipal(?string $value)`:
  ```php
  $stripped = preg_replace('/^(?:PT|CV|UD|PD|NV)\.?\s+/ui', '', $collapsed);
  ```
  lalu periksa alias kamus.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/CsvImportNormalizationTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/TextNormalizer.php tests/Unit/CsvImportNormalizationTest.php
git commit -m "feat(normalization): strip business entity prefixes PT/CV and add bernofarm alias"
```

---

### Task 4: Artisan Command `formulary:normalize-principals` with `--dry-run`

**Files:**
- Create: `app/Console/Commands/NormalizePrincipalsCommand.php`
- Create: `tests/Feature/NormalizePrincipalsCommandTest.php`

**Interfaces:**
- Consumes: Model `ProposalItem` dan `ReferenceMedicine`, `TextNormalizer`
- Produces: Command CLI `php artisan formulary:normalize-principals {--dry-run}` yang memperbarui data historis secara transaksional atau pratinjau aman.

- [ ] **Step 1: Write the failing test**

Buat `tests/Feature/NormalizePrincipalsCommandTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\ReferenceMedicine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews principal normalizations without mutating database in dry-run mode', function (): void {
    $proposal = Proposal::create(['doctor_name' => 'dr. Budi', 'proposal_date' => '2026-11-17']);
    $item = $proposal->items()->create(['medicine_name' => 'Obat A', 'principal_name' => 'PT. Novell']);
    $ref = ReferenceMedicine::create(['medicine_name' => 'Obat B', 'principal_name' => 'PT. Bernofarm']);

    $this->artisan('formulary:normalize-principals', ['--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect($item->fresh()->principal_name)->toBe('PT. Novell')
        ->and($ref->fresh()->principal_name)->toBe('PT. Bernofarm');
});

it('applies principal normalizations in database when run without dry-run', function (): void {
    $proposal = Proposal::create(['doctor_name' => 'dr. Budi', 'proposal_date' => '2026-11-17']);
    $item = $proposal->items()->create(['medicine_name' => 'Obat A', 'principal_name' => 'PT. Novell']);
    $ref = ReferenceMedicine::create(['medicine_name' => 'Obat B', 'principal_name' => 'PT. Bernofarm']);

    $this->artisan('formulary:normalize-principals')
        ->assertSuccessful();

    expect($item->fresh()->principal_name)->toBe('Novell')
        ->and($ref->fresh()->principal_name)->toBe('Berno');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/NormalizePrincipalsCommandTest.php`
Expected: FAIL (`Command "formulary:normalize-principals" is not defined`).

- [ ] **Step 3: Implement `NormalizePrincipalsCommand.php`**

Buat `app/Console/Commands/NormalizePrincipalsCommand.php` dengan signature `formulary:normalize-principals {--dry-run : Pratinjau perubahan tanpa mutasi basis data}`:
- Memindai `ProposalItem` dan `ReferenceMedicine` yang memiliki `principal_name`.
- Bandingkan dengan `TextNormalizer::normalizePrincipal(...)`.
- Jika `--dry-run`, render table output.
- Jika live, eksekusi dalam `DB::transaction(...)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/NormalizePrincipalsCommandTest.php`
Expected: PASS (2 tests pass).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/NormalizePrincipalsCommand.php tests/Feature/NormalizePrincipalsCommandTest.php
git commit -m "feat(cli): add formulary:normalize-principals command with dry-run support"
```

---

### Task 5: Server-Side Query Whitelist & Multi-Column Sorting

**Files:**
- Modify: `app/Queries/ProposalItemRecapQuery.php`
- Modify: `app/Http/Controllers/ProposalRecapController.php`
- Modify: `app/Http/Controllers/ProposalExportController.php`
- Create: `tests/Feature/ProposalRecapSortingTest.php`

**Interfaces:**
- Consumes: `$filters['sort_by']` dan `$filters['sort_dir']`
- Produces: Query Eloquent terurut dengan proteksi `SORT_MAP` whitelist dan tie-breaker `proposal_items.id`; ekspor XLSX sinkron dengan sortir aktif; live CSV feed tetap kronologis default.

- [ ] **Step 1: Write the failing test**

Buat `tests/Feature/ProposalRecapSortingTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sorts proposal recap items by doctor, medicine, date, and principal with whitelist protection', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. Zaidan', 'proposal_date' => '2026-11-01']);
    $p1->items()->create(['medicine_name' => 'Amoxicillin', 'principal_name' => 'Sanbe']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Ahmad', 'proposal_date' => '2026-11-10']);
    $p2->items()->create(['medicine_name' => 'Zithromax', 'principal_name' => 'Pfizer']);

    // Sort by doctor asc
    $resp = $this->get(route('proposals.index', ['sort_by' => 'doctor', 'sort_dir' => 'asc']))->assertOk();
    $content = $resp->getContent();
    expect(strpos($content, 'dr. Ahmad'))->toBeLessThan(strpos($content, 'dr. Zaidan'));

    // Malicious sort_by falls back safely to default order
    $this->get(route('proposals.index', ['sort_by' => 'id;DROP TABLE proposals;--', 'sort_dir' => 'asc']))
        ->assertOk();
});

it('ensures live csv export ignores sort_by and stays chronological default', function (): void {
    config(['formulary.feed_token' => 'secret-test-token']);

    $p1 = Proposal::create(['doctor_name' => 'dr. Zaidan', 'proposal_date' => '2026-11-01']);
    $p1->items()->create(['medicine_name' => 'Amoxicillin', 'principal_name' => 'Sanbe']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Ahmad', 'proposal_date' => '2026-11-10']);
    $p2->items()->create(['medicine_name' => 'Zithromax', 'principal_name' => 'Pfizer']);

    $resp = $this->get('/exports/proposals.csv?token=secret-test-token&sort_by=doctor&sort_dir=asc')->assertOk();
    $csv = $resp->streamedContent();

    // Default chronological is desc: p2 (2026-11-10) appears before p1 (2026-11-01)
    expect(strpos($csv, 'dr. Ahmad'))->toBeLessThan(strpos($csv, 'dr. Zaidan'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ProposalRecapSortingTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement sorting in `ProposalItemRecapQuery.php` and Controllers**

1. Di `app/Queries/ProposalItemRecapQuery.php`:
   - Definisikan `private const SORT_MAP = ['date' => 'proposals.proposal_date', 'period' => 'proposals.period_year', 'doctor' => 'proposals.doctor_name', 'medicine' => 'proposal_items.medicine_name', 'principal' => 'proposal_items.principal_name', 'created_at' => 'proposal_items.created_at'];`
   - Periksa `$sortBy = $filters['sort_by'] ?? null;`
   - Jika terdaftar di `SORT_MAP`: `$query->orderBy(self::SORT_MAP[$sortBy], $sortDir)->orderBy('proposal_items.id');`
   - Else fallback default: `$query->orderByDesc('proposals.proposal_date')->orderByDesc('proposals.id')->orderBy('proposal_items.id');`
2. Di `app/Http/Controllers/ProposalRecapController.php`:
   - Teruskan `sort_by` dan `sort_dir` dari `$request->query(...)` ke `$filters` dan view.
3. Di `app/Http/Controllers/ProposalExportController.php`:
   - Pada `xlsx()` sertakan `sort_by` dan `sort_dir` dalam `$filters`.
   - Pada `csv()` lewati sorting kustom agar tetap kronologis default.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/ProposalRecapSortingTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Queries/ProposalItemRecapQuery.php app/Http/Controllers/ProposalRecapController.php app/Http/Controllers/ProposalExportController.php tests/Feature/ProposalRecapSortingTest.php
git commit -m "feat(recap): implement server-side query whitelist sorting with deterministic tie-breaker"
```

---

### Task 6: Blade Reusable Component `<x-table-sort-header>` & Recap View Integration

**Files:**
- Create: `resources/views/components/table-sort-header.blade.php`
- Modify: `resources/views/proposals/index.blade.php`
- Test: `tests/Feature/ProposalRecapFilterTest.php`

**Interfaces:**
- Consumes: Props `column`, `label`, `currentSortBy`, `currentSortDir`, `defaultDir`
- Produces: Header tabel TH interaktif dengan visual panah (↑, ↓, ⇅) dan preservasi filter form pencarian via hidden inputs.

- [ ] **Step 1: Write the failing test**

Tambahkan assertion di `tests/Feature/ProposalRecapFilterTest.php`:
```php
it('renders interactive sortable table headers with active direction indicators and hidden sort inputs in filter form', function (): void {
    $response = $this->get(route('proposals.index', ['sort_by' => 'doctor', 'sort_dir' => 'asc']))->assertOk();

    // Renders sort header links
    $response->assertSee('sort_by=doctor', false)
        ->assertSee('sort_dir=desc', false) // Next toggle direction for active column
        ->assertSee('sort_by=medicine', false);

    // Filter form carries hidden inputs
    $response->assertSee('<input type="hidden" name="sort_by" value="doctor">', false)
        ->assertSee('<input type="hidden" name="sort_dir" value="asc">', false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/ProposalRecapFilterTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement `table-sort-header.blade.php` and update `index.blade.php`**

1. Buat `resources/views/components/table-sort-header.blade.php`:
   - Hitung `$isActive = $currentSortBy === $column;`
   - Hitung `$nextDir = $isActive ? ($currentSortDir === 'asc' ? 'desc' : 'asc') : ($defaultDir ?? 'asc');`
   - Link: `request()->fullUrlWithQuery(['sort_by' => $column, 'sort_dir' => $nextDir, 'page' => null])`
   - Render indikator ikon panah zamrud untuk aktif, dan abu-abu netral untuk inaktif.
2. Update `resources/views/proposals/index.blade.php`:
   - Bungkus kolom header tabel (Tanggal Usulan, Periode, Nama Dokter, Nama Obat, Pabrikan, Waktu Input) dengan `<x-table-sort-header>`.
   - Sisipkan hidden inputs `sort_by` dan `sort_dir` di dalam form filter pencarian.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/ProposalRecapFilterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/table-sort-header.blade.php resources/views/proposals/index.blade.php tests/Feature/ProposalRecapFilterTest.php
git commit -m "feat(ui): add reusable table-sort-header component and integrate interactive sorting in recap view"
```

---

### Task 7: End-to-End Quality Gate & Release Verification for `v1.0.2`

**Files:**
- Verify: Seluruh berkas yang dimodifikasi

**Interfaces:**
- Consumes: Seluruh artefak kode dan uji Tasks 1–6.
- Produces: 100% lulus pada seluruh suite uji Pest PHP (minimal 35 tests, 200+ assertions), nihil pelanggaran style PHP Laravel Pint, serta build produksi Vite yang valid.

- [ ] **Step 1: Run complete Pest PHP test suite**

Run: `./vendor/bin/pest`
Expected: PASS (all tests pass).

- [ ] **Step 2: Run Laravel Pint code styling check**

Run: `./vendor/bin/pint --test`
Expected: `{"tool":"pint","result":"passed"}` (0 violations).

- [ ] **Step 3: Run Vite asset compilation for production**

Run: `npm run build`
Expected: Output `✓ built in ...` dengan berkas aset manifest terbit di `public/build`.

- [ ] **Step 4: Verify working tree cleanliness**

Run: `git status`
Expected: `nothing to commit, working tree clean`.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-17-v1-0-2-recap-interactive-ordering-hardening-plan.md`.
Siap dieksekusi secara otonom melalui **Subagent-Driven Development (SDD)**.
