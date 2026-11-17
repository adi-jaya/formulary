# Design Document: FormulariumRS v1.0.2 — Interactive Ordering Rekapitulasi & Data Hardening

**Tanggal:** 2026-11-15  
**Target Rilis:** `v1.0.2`  
**Status:** Disetujui (Builder Review Passed: Ready for Tier 2 Implementation Plan)  
**Dokumen Terkait:** [ROADMAP.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/ROADMAP.md), [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md)

---

## 1. Intent & The Why

Pada rilis `v1.0.0` dan `v1.0.1`, data usulan obat pada halaman rekapitulasi (`/proposals`) disajikan dalam urutan kronologis tetap (`proposal_date` DESC, `id` DESC). Ketika evaluasi berkas formularium berlangsung, Tim Farmasi dan Komite Farmasi dan Terapi (KFT) perlu menelaah ratusan usulan dengan berbagai sudut pandang:
1. Memeriksa seluruh obat yang diajukan oleh dokter spesialis tertentu secara alfabetis nama obat.
2. Memeriksa portofolio usulan obat dari pabrikan/principle tertentu.
3. Memeriksa urutan waktu masuk data terbaru (`created_at`) untuk memantau usulan susulan.

Sebelumnya, staf terpaksa mengekspor data ke Microsoft Excel atau Google Sheets hanya untuk mengurutkan tabel. 

Selain itu, audit data menunjukkan perlunya penguatan sistem:
- **Performa Basis Data:** Tabel `proposals` belum memiliki indeks pada `doctor_name` dan indeks komposit `['period_year', 'proposal_date']` untuk mempercepat query multi-filter.
- **Sanitasi CSV OWASP:** Fungsi `SpreadsheetSanitizer` belum menangani karakter spasi tersembunyi di awal string (*leading whitespace*) dan belum mencakup karakter berisiko formula injection seperti pipa (`|`) dan persentase (`%`).
- **Konsistensi Nama Pabrikan:** Input nama pabrikan sering memuat awalan badan usaha seperti `PT.`, `CV.`, atau variasi `Bernofarm` padahal kamus resmi rumah sakit menggunakan nama bersih `Berno`.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
- **Interactive Multi-Column Ordering:** Memungkinkan pengurutan tabel rekapitulasi berdasarkan: Tanggal Usulan, Periode, Nama Dokter, Nama Obat, Pabrikan/Principle, dan Waktu Input (ASC & DESC).
- **Smart Contextual Direction:** Klik pertama pada kolom teks (Dokter, Obat, Pabrikan) otomatis mengurutkan **ASC** (A-Z); klik pertama pada kolom tanggal/waktu (Tanggal Usulan, Waktu Input) otomatis mengurutkan **DESC** (terbaru). Klik berikutnya membalik arah (*toggle*).
- **Strict Query Whitelist Guard:** Mencegah SQL injection dengan membatasi kolom pengurutan pada daftar putih (*whitelist map*) di `ProposalItemRecapQuery`.
- **Preservasi Filter & Deterministic Pagination:** Mempertahankan seluruh parameter pencarian aktif (`period_year`, `doctor`, `principal`, `search`, dll.) saat mengurutkan kolom, me-reset parameter `page` ke halaman 1, menyertakan input tersembunyi `sort_by` dan `sort_dir` pada form filter pencarian, dan menyertakan `proposal_items.id` sebagai *tie-breaker* agar paginasi SQLite stabil.
- **Ekspor Excel (.xlsx) Mengikuti Sortir Aktif:** Memastikan unduhan berkas Excel melalui tombol "Unduh XLSX" mencerminkan kolom dan arah pengurutan yang sedang aktif di layar.
- **Reusable Blade Sort Header Component:** Menyediakan komponen `<x-table-sort-header>` yang membungkus header tabel dengan tautan interaktif dan ikon status (↑, ↓, ⇅). Kolom "Tanggal Usulan" secara default berstatus aktif DESC (`↓`).
- **Indeks Komposit Basis Data:** Migrasi aditif penambahan indeks pada `proposals.doctor_name` dan composite index `['period_year', 'proposal_date']`.
- **Hardening Sanitasi OWASP CSV:** Memangkas leading whitespace (`ltrim`) dan memasukkan karakter bahaya `|` dan `%` pada `SpreadsheetSanitizer`.
- **Pembersihan Awalan Badan Usaha & Kamus Pabrikan:** Regex pembersih awalan `PT`, `CV`, `UD`, `PD`, `NV` pada `TextNormalizer`, penyelarasan alias `'bernofarm' => 'Berno'`, serta Artisan Command rekonsiliasi data historis `php artisan formulary:normalize-principals` dengan opsi `--dry-run`.

### Non-Goals
- Tidak mengimplementasikan pengurutan tabel hanya di sisi klien DOM JavaScript (karena merusak integritas data lintas halaman paginasi).
- Tidak menambahkan dependency pihak ketiga seperti Livewire, DataTables, atau HTMX.
- Tidak mengubah kontrak kolom rute ekspor `/exports/proposals.csv` atau `/exports/proposals.xlsx`. Rute live CSV feed (`/exports/proposals.csv`) yang digunakan formula `=IMPORTDATA()` Google Sheets tetap dikunci secara ketat pada urutan kronologis default agar formula spreadsheet pihak ketiga tidak mengalami pergeseran baris data.

### Anti-Goals
- **Dilarang memasukkan input mentah pengguna ke klausa `ORDER BY` SQL:** Semua pengurutan wajib melalui pemetaan array tertutup.
- **Dilarang merusak skema lama:** Migrasi basis data bersifat aditif (`Schema::table`) tanpa mengubah atau menghapus kolom existing.
- **Dilarang memutasi data saat `--dry-run` aktif:** Command Artisan rekonsiliasi wajib murni berstatus pratinjau (read-only) saat flag `--dry-run` digunakan.

---

## 3. Constraints

- **Prinsip AGENTS.md:**
  - Wajib `declare(strict_types=1);` di setiap file PHP baru atau yang dimodifikasi.
  - Mematuhi arsitektur monolitik tanpa Generic Repository Pattern.
  - SQLite WAL (`busy_timeout = 5000`).
  - Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS.
  - Standarisasi kode Laravel Pint (`./vendor/bin/pint --test`) wajib bersih.

---

## 4. Approaches

### Opsi yang Dipertimbangkan:
1. **Opsi 1: Server-Side Query Whitelist + Reusable Blade Component (Dipilih)**
   - Pengurutan dieksekusi di level database via Eloquent Builder.
   - Paginasi tetap konsisten dan akurat untuk seluruh baris data.
   - Header tabel ditenagai komponen Blade reusable dengan styling Tailwind v4 dan visual panah yang informatif.
   - *Alasan Pemilihan:* Menjamin kebenaran data audit rumah sakit, kebal SQL injection, dan mempertahankan arsitektur *lean* tanpa dependensi eksternal.

2. **Opsi 2: Client-Side DOM Sorting via Alpine.js**
   - Hanya mengurutkan 25 elemen HTML di layar peramban.
   - *Alasan Ditolak:* Gagal total saat pengguna mencari obat yang berada di halaman paginasi 2 atau berikutnya.

3. **Opsi 3: Partial Page Swapping via Livewire / HTMX**
   - Mengganti elemen tabel secara asinkronus via AJAX.
   - *Alasan Ditolak:* Menambah kompleksitas runtime dan dependensi eksternal yang tidak diperlukan untuk aplikasi LAN rumah sakit dengan SQLite berlatensi < 40ms.

---

## 5. Detailed Architecture & Design Decisions

### 5.1 Query Object Whitelist (`app/Queries/ProposalItemRecapQuery.php`)

Menambahkan parameter pengurutan terproteksi pada `ProposalItemRecapQuery::build(array $filters = [])`:

1. **Whitelist Mapping Kolom:**
   ```php
   private const SORT_MAP = [
       'date' => 'proposals.proposal_date',
       'period' => 'proposals.period_year',
       'doctor' => 'proposals.doctor_name',
       'medicine' => 'proposal_items.medicine_name',
       'principal' => 'proposal_items.principal_name',
       'created_at' => 'proposal_items.created_at',
   ];
   ```
2. **Logika Sorting:**
   - Parameter input: `$filters['sort_by'] ?? null` dan `$filters['sort_dir'] ?? null`.
   - Validasi arah: `$sortDir = strtolower((string) ($filters['sort_dir'] ?? '')) === 'asc' ? 'asc' : 'desc'`.
   - Jika `sort_by` valid dan terdaftar di `SORT_MAP`:
     - Query diurutkan berdasarkan kolom SQL terkait sesuai `$sortDir`.
     - Ditambahkan tie-breaker: `->orderBy('proposal_items.id')`.
   - Jika `sort_by` tidak valid atau kosong (Default):
     - Mempertahankan urutan default rilis sebelumnya:
       ```php
       $query->orderByDesc('proposals.proposal_date')
             ->orderByDesc('proposals.id')
             ->orderBy('proposal_items.id');
       ```

### 5.2 Controller Integration (`ProposalRecapController.php` & `ProposalExportController.php`)

1. **`ProposalRecapController.php`:**
   Menangkap parameter `sort_by` dan `sort_dir` dari request:
   ```php
   $rawSortBy = $request->query('sort_by');
   $sortBy = $rawSortBy !== null && $rawSortBy !== '' ? (string) $rawSortBy : 'date';
   $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

   $filters = [
       'period_year' => $request->query('period_year'),
       'doctor' => $request->query('doctor'),
       'principal' => $request->query('principal'),
       'date_from' => $request->query('date_from'),
       'date_to' => $request->query('date_to'),
       'search' => $request->query('search'),
       'sort_by' => $sortBy,
       'sort_dir' => $sortDir,
   ];
   ```
   Meneruskan `$sortBy` dan `$sortDir` ke view `proposals.index`.

2. **`ProposalExportController.php`:**
   - **Method `xlsx()`:** Meneruskan `sort_by` dan `sort_dir` ke dalam array `$filters` yang diteruskan ke `ProposalExportService::streamXlsx($filters)`, sehingga susunan baris pada file Excel hasil unduhan persis mencerminkan urutan yang sedang aktif di layar peramban.
   - **Method `csv()` (Live Feed Google Sheets):** Sengaja mengabaikan parameter sorting dan selalu menggunakan urutan kronologis default agar rumus `=IMPORTDATA(...)` yang aktif di spreadsheet rumah sakit tidak bergeser.

### 5.3 Komponen Blade & Markup Form (`resources/views/`)

1. **Komponen Header Sortir Reusable (`resources/views/components/table-sort-header.blade.php`):**
   - **Props:**
     - `column`: Identifier kolom URL (`'date'`, `'doctor'`, `'medicine'`, `'principal'`, `'created_at'`).
     - `label`: Label teks header (misal: `'Nama Obat Diusulkan'`).
     - `currentSortBy`: Kolom yang sedang aktif disortir.
     - `currentSortDir`: Arah sortir yang sedang aktif (`'asc'` atau `'desc'`).
     - `defaultDir`: Arah bawaan saat pertama kali diklik (`'asc'` untuk teks, `'desc'` untuk tanggal/waktu).
   - **Logika URL & Arah:**
     - Jika `currentSortBy === column`: Arah berikutnya adalah kebalikan dari `currentSortDir` (`currentSortDir === 'asc' ? 'desc' : 'asc'`).
     - Jika `currentSortBy !== column`: Arah berikutnya adalah `defaultDir`.
     - Link dibentuk via:
       ```php
       request()->fullUrlWithQuery([
           'sort_by' => $column,
           'sort_dir' => $nextDir,
           'page' => null, // Reset ke halaman 1
       ])
       ```
   - **Visual Indikator Status:**
     - **Aktif ASC (↑):** Panah atas berwarna zamrud tebal (`text-emerald-700`).
     - **Aktif DESC (↓):** Panah bawah berwarna zamrud tebal (`text-emerald-700`).
     - **Netral (⇅):** Ikon panah ganda halus berwarna abu-abu (`text-slate-300 group-hover:text-slate-500`).

2. **Preservasi Sortir pada Form Filter (`resources/views/proposals/index.blade.php`):**
   Di dalam tag `<form>` filter pencarian, ditambahkan input tersembunyi agar aksi tombol "Terapkan Filter" tidak me-reset sorting yang sedang aktif:
   ```blade
   @if(request('sort_by'))
       <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
       <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">
   @endif
   ```

### 5.4 Migrasi Indeks Basis Data

Berkas migrasi aditif baru: `database/migrations/YYYY_MM_DD_000001_add_indexes_to_proposals_table.php`:
```php
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
```

### 5.5 Hardening Sanitasi OWASP CSV (`app/Support/SpreadsheetSanitizer.php`)

Pembaruan pada `SpreadsheetSanitizer`:
1. Daftar karakter berbahaya diperluas:
   ```php
   private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r", '|', '%'];
   ```
2. Logika sanitasi memangkas spasi tersembunyi di awal nilai sebelum mengevaluasi karakter pertama:
   ```php
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
   ```

### 5.6 Normalisasi Awalan Badan Usaha & Kamus Pabrikan (`app/Support/TextNormalizer.php`)

1. **Pembersihan Awalan Badan Usaha:**
   - Menghapus awalan badan usaha umum di Indonesia seperti `PT`, `PT.`, `CV`, `CV.`, `UD`, `UD.`, `PD`, `PD.`, `NV`, `NV.` (secara case-insensitive).
   - Regex: `/^(?:PT|CV|UD|PD|NV)\.?\s+/ui`
2. **Penyelarasan Alias Pabrikan:**
   - Menambahkan `'bernofarm' => 'Berno'` pada `PRINCIPAL_ALIASES` agar terpetakan ke nama resmi di basis data referensi.
3. **Alur di `normalizePrincipal(?string $value)`:**
   - Collapse whitespace ➔ Strip dash/empty ➔ Bersihkan prefix badan usaha ➔ Cek alias kamus.

### 5.7 Artisan Command Rekonsiliasi Data Historis (`app/Console/Commands/NormalizePrincipalsCommand.php`)

Command Artisan untuk merapikan data lama di tabel `proposal_items` dan `reference_medicines`:
- **Signature:** `formulary:normalize-principals {--dry-run : Menampilkan pratinjau perubahan tanpa memutasi basis data}`
- **Alur Kerja:**
  1. Memindai data pada `proposal_items` dan `reference_medicines` yang memiliki `principal_name`.
  2. Mengevaluasi `TextNormalizer::normalizePrincipal($item->principal_name)`.
  3. Mengidentifikasi record yang nilainya berbeda setelah dinormalisasi.
  4. Jika `--dry-run`:
     - Menampilkan tabel pratinjau (ID, Nilai Lama, Nilai Baru).
     - Menampilkan rekapitulasi: *"Pratinjau selesai: X record teridentifikasi untuk diperbarui. Jalankan tanpa --dry-run untuk mengeksekusi."*
  5. Jika mode eksekusi nyata:
     - Menjalankan update di dalam `DB::transaction(...)`.
     - Memberikan laporan jumlah record yang sukses diperbarui.

---

## 6. What's Left to the Builder

- Membuat berkas migrasi indeks basis data dan memverifikasi kompatibilitasnya dengan SQLite test suite.
- Menulis test suite komprehensif di Pest PHP:
  - Unit test `SpreadsheetSanitizerTest` untuk karakter `|`, `%`, dan leading whitespace.
  - Unit test `TextNormalizerTest` untuk stripping PT/CV dan alias Bernofarm.
  - Feature test `ProposalRecapSortingTest` untuk memverifikasi setiap kolom dapat diurutkan (ASC/DESC), whitelist guard menolak parameter acak, serta filter existing tetap terjaga.
  - Feature test `NormalizePrincipalsCommandTest` untuk memvalidasi mode `--dry-run` vs mode eksekusi transaksional.
- Menjalankan `./vendor/bin/pest` untuk memastikan 100% lulus.
- Menjalankan `./vendor/bin/pint --test` untuk memastikan kebersihan format kode.
- Menjalankan `npm run build` untuk memverifikasi integrasi Blade dan Tailwind v4.
