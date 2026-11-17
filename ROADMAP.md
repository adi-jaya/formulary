# Rangkuman Komprehensif: Arsitektur & Roadmap Pembaruan FormulariumRS (`v1.0.x` — `v1.x.0`)

**Sistem:** [FormulariumRS](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS) — Sistem Usulan Obat Formularium Rumah Sakit Aisyiyah Siti Fatimah Tulangan  
**Baseline Operasional:** Rilis Stabil `v1.2.1` (88 Pest Tests Passing, 510 Assertions, Git Working Tree Bersih)  
**Dokumen Rujukan Utama:** [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md) dan [docs/DOKUMENTASI_SISTEM.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/docs/DOKUMENTASI_SISTEM.md)

---

## 1. Landasan Prinsip Arsitektur & Batasan Mutlak

Seluruh saran dan rencana pengembangan dirancang dengan mematuhi batasan non-negosiasi berikut:
1. **Zero-Friction DPJP**: Formulir pengajuan dokter (`GET /` dan `POST /proposals`) **wajib tetap bebas login/auth/PIN** agar tidak menghambat penginputan di sela visitasi bangsal atau poliklinik.
2. **Arsitektur Tanpa Repository Pattern**: Mempertahankan alur fungsional monolitik yang sudah ada: `Controller` ➔ `Service` ➔ `Query Object` / `Model`.
3. **Database Engine & Konkurensi**: Tetap mengandalkan **SQLite 3** dengan mode `WAL` (*Write-Ahead Logging*), `busy_timeout = 5000`, dan `synchronous = NORMAL`.
4. **Stack Frontend Bebas Ketergantungan Eksternal**: Mempertahankan **Blade Components**, **Tailwind CSS v4**, dan **Alpine.js lokal** (dibundel via Vite, bebas CDN eksternal).
5. **Ekspor & Streaming**: Mengandalkan **OpenSpout** (XLSX streaming < 10 MB) dan native PHP streaming `fputcsv()` untuk live CSV feed spreadsheet.

---

## 2. Temuan Audit Kritis & Fakta Basis Kode (*Ground Truth*)

Berdasarkan audit langsung pada kode sumber, telah diidentifikasi beberapa poin teknis penting yang mendasari keputusan teknis:

- **Visibilitas Method Sync Dokter**: Pada [`DoctorDirectoryService.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Services/DoctorDirectoryService.php#L105), method `resolveBaseDoctors()` berstatus `private`. Perlu penambahan public method `refreshFromApi(): array` atau invalidasi `Cache::forget('formulary.doctors.api_list')`.
- **Kamus Principle Eksisting**: Dataset aktif di [`database/seeders/data/formulary_medicines.csv`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/database/seeders/data/formulary_medicines.csv) sudah memuat nama resmi `'Berno'`. Pemetaan alias harus diarahkan ke `'Berno'` (bukan `'Bernofarm'`) agar tidak memecah opsi pada filter dropdown.
- **Batasan Argumen SQLite `GROUP_CONCAT`**: SQLite hanya menerima 1 argumen pada fungsi `GROUP_CONCAT(DISTINCT ...)`. Gelar dokter yang memuat tanda koma (misal: `dr. X, Sp.A`) harus ditangani dengan pemisah yang aman.
- **Risiko Formula Google Sheets `#REF!`**: Penambahan kolom baru pada `/exports/proposals.csv` secara default berisiko merusak rumus `=IMPORTDATA(...)` yang sedang aktif di spreadsheet farmasi karena terjadinya *array spill conflict*. Solusinya adalah mempertahankan kontrak default 8 kolom lama dan menyediakan kolom baru via query parameter opt-in (`&include_review=1`).
- **Zero-Latency Mobile via Laravel 12 [`defer()`](https://laravel.com/docs/12.x/helpers#deferred-functions)**: Webhook chat/notifikasi eksternal dieksekusi setelah respons HTTP selesai dihantarkan ke peramban dokter, sehingga dokter tidak mengalami penundaan loading.
- **Kompatibilitas Mobile & iOS**: Webkit/iOS Safari tidak mendukung Web Vibration API (`navigator.vibrate`), sehingga haptic dipadukan dengan animasi visual mikro (pulse zamrud), serta penyesuaian `safe-area-inset-bottom` dan `visualViewport` agar tombol submit tidak bertabrakan dengan keyboard virtual.

---

## 3. Spesifikasi Rencana Pembaruan & Roadmap Versi

---

### A. Kategori PATCH (`v1.0.x`) — Quick Wins, Hardening & DX

#### 1. Versi `v1.0.1` — UX/UI Micro-Polish pada Formulir Dokter
* **Guard Tab Unsaved (`beforeunload`)**: Mencegah data hilang akibat swipe-back atau tab tertutup jika terdapat baris obat yang sudah terisi.
* **Auto-Save Draf Lokal (`localStorage`)**: Menyimpan state input secara otomatis (`formulary_draft_v1`) dengan mekanisme proteksi tabrakan (draf otomatis diabaikan bila terdapat nilai `old()` hasil kegagalan validasi atau flash session sukses).
* **Shortcut Keyboard & Auto-Focus**: Dukungan `Ctrl+Enter` / `Cmd+Enter` untuk submit formulir langsung dari baris mana pun, serta pemindahan fokus kursor ke baris obat baru secara otomatis (`this.$nextTick()`).
* **Counter Karakter Dinamis**: Indikator kuota karakter `x-text` (`0/2.000`) pada textarea alasan klinis dan catatan pengusul.
* **Safe-Area Floating Action Bar**: Tombol aksi mobile dengan `env(safe-area-inset-bottom)` yang otomatis disembunyikan saat keyboard virtual aktif (`visualViewport`).
* **Komponen:** [`resources/js/app.js`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/resources/js/app.js), [`resources/views/proposals/create.blade.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/resources/views/proposals/create.blade.php).
* **Estimasi:** Kompleksitas **Rendah**, Risiko **Rendah**.

---

#### 2. Versi `v1.0.2` — Keamanan Data, Performa & Interactive Ordering Rekapitulasi
* **Interactive Multi-Column Ordering (Permintaan Tambahan UX/UI)**:
  - Kemampuan mengurutkan tabel rekapitulasi berdasarkan: Tanggal Usulan, Periode, Nama Dokter, Nama Obat, Pabrikan/Principle, dan Waktu Input (ASC/DESC).
  - Tautan header tabel interaktif dengan indikator panah status (↑ / ↓ / ⇅).
  - *Strict Whitelist Guard* di [`ProposalItemRecapQuery.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Queries/ProposalItemRecapQuery.php) untuk mencegah SQL injection.
  - Preservasi query string penuh (`request()->fullUrlWithQuery(...)`) sehingga filter pencarian yang sedang aktif tidak hilang saat kolom diurutkan.
  - Paginasi tetap stabil dengan *deterministic tie-breaker* (`orderBy('proposal_items.id')`).
* **Indeks Komposit Basis Data**: Migrasi aditif penambahan indeks pada `proposals.doctor_name` dan composite index `['period_year', 'proposal_date']` untuk akselerasi join query.
* **Hardening Sanitasi OWASP CSV**: Pemotongan leading whitespace (`ltrim`) dan penambahan karakter bahaya `|` dan `%` pada [`SpreadsheetSanitizer.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Support/SpreadsheetSanitizer.php).
* **Pembersihan Awalan Badan Usaha (PT/CV) & Kamus Pabrikan**: Regex pembersihan prefix badan hukum pada [`TextNormalizer.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Support/TextNormalizer.php), penyelarasan alias ke nama resmi basis data RS (`'bernofarm' => 'Berno'`), serta command rekonsiliasi data historis `php artisan formulary:normalize-principals`.
* **Komponen:** [`app/Queries/ProposalItemRecapQuery.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Queries/ProposalItemRecapQuery.php), [`app/Http/Controllers/ProposalRecapController.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Http/Controllers/ProposalRecapController.php), [`resources/views/proposals/index.blade.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/resources/views/proposals/index.blade.php), [`app/Support/SpreadsheetSanitizer.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Support/SpreadsheetSanitizer.php), [`app/Support/TextNormalizer.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Support/TextNormalizer.php).
* **Estimasi:** Kompleksitas **Rendah**, Risiko **Rendah**.

---

#### 3. Versi `v1.0.3` — Otomatisasi Operasional & Tooling CLI IT
* **Hot-Backup Atomic SQLite (`VACUUM INTO`)**: Artisan command `php artisan formulary:backup` yang memanfaatkan engine bawaan SQLite untuk menyalin basis data secara konsisten tanpa risiko konflik file lock WAL, dilengkapi rotasi retensi 30 hari.
* **CLI Sync Dokter Portal Sifast**: Artisan command `php artisan formulary:sync-doctors` yang memanggil public method baru `DoctorDirectoryService::refreshFromApi()` untuk memperbarui cache dan berkas snapshot dokter secara terjadwal via cron.
* **Komponen:** [`app/Services/DoctorDirectoryService.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/app/Services/DoctorDirectoryService.php), [`routes/console.php`](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/routes/console.php), Artisan Commands baru.
* **Estimasi:** Kompleksitas **Rendah**, Risiko **Rendah**.

---

### B. Kategori MINOR (`v1.x.0`) — Fitur Baru Bernilai Tambah

#### 1. Versi `v1.1.0` — Analitik Konsensus Usulan Obat Sidang KFT
* **Tab Analitik Konsensus Usulan & Matriks Permohonan Ganda**:
  - Tab segmented switch di halaman rekapitulasi (`tab=items|consensus`).
  - Query Object `ProposalConsensusQuery.php` untuk mengelompokkan obat yang diusulkan oleh beberapa dokter spesialis sekaligus (`COUNT(DISTINCT doctor_name)`), disajikan dalam tampilan kartu accordion interaktif.
  - Menampilkan peringkat obat berdasarkan dukungan dokter terbanyak, daftar DPJP pengusul (beserta poli/klinik), variasi principle pabrikan, dan rincian justifikasi klinis masing-masing dokter.
  - Filter interaktif: Tahun Periode, Pencarian Kata Kunci Obat, serta Quick Filter Chip *"Semua Usulan"* vs *"Hanya Usulan Konsensus (≥ 2 Dokter)"*.
* **Komponen:** `app/Queries/ProposalConsensusQuery.php`, `app/Http/Controllers/ProposalRecapController.php`, `resources/views/proposals/index.blade.php`, `resources/views/proposals/partials/consensus-tab.blade.php`.
* **Estimasi:** Kompleksitas **Rendah - Sedang**, Risiko **Rendah** (Fitur pembacaan independen tanpa mutasi data).

---

#### 2. Versi `v1.2.0` — Modul Telaah KFT Farmasi & Sinkronisasi Real-Time Google Sheets
* **Modul Status Telaah KFT Internal Farmasi**:
  - Kolom aditif pada `proposal_items`: `review_status` (*pending*, *disetujui*, *ditolak*, *substitusi*), `review_notes`, dan `reviewed_at`.
  - **Safeguard Google Sheets Anti `#REF!`**: URL export `/exports/proposals.csv` default tetap mempertahankan kontrak 8 kolom lama agar formula `=IMPORTDATA(...)` pasif tidak pecah. Kolom telaah dibuka via parameter opt-in `&include_review=1`.
  - **Form Dokter Tetap Zero-Friction**: Pengubahan status telaah dilindungi passcode sesi Farmasi berbasis `FORMULARY_REVIEW_SECRET` di `.env` (diinput sekali per sesi browser di halaman rekapitulasi).
* **Sinkronisasi Real-Time Google Sheets Tanpa Jeda (*Zero-Latency Event Push*)**:
  - Mengatasi keterbatasan caching 1–2 jam formula `=IMPORTDATA()` Google Sheets dengan beralih ke model *Event-Driven Push* langsung ke Google Apps Script Webhook.
  - Script Google Apps Script terpasang di Google Sheet Farmasi rumah sakit (`doPost(e)` yang mengeksekusi `sheet.appendRows(...)`).
  - Laravel 12 [`defer()`](https://laravel.com/docs/12.x/helpers#deferred-functions) mengeksekusi HTTP POST ke Webhook Google Apps Script tepat setelah dokter menekan submit, menghasilkan data seketika muncul di spreadsheet dalam waktu < 2 detik tanpa menambah beban loading dokter.
  - Command Artisan `php artisan formulary:sync-sheets` untuk melakukan sinkronisasi awal atau rekonsiliasi berkala seluruh data ke spreadsheet.
* **Komponen:** Migrasi aditif baru, `app/Services/GoogleSheetSyncService.php`, `app/Console/Commands/SyncGoogleSheetCommand.php`, `app/Http/Controllers/ProposalController.php`, `config/formulary.php`.
* **Estimasi:** Kompleksitas **Sedang**, Risiko **Rendah - Sedang**.

---

## 4. Matriks Rilis & Roadmap Proyek

```text
  v1.0.1 (Quick Polish)      v1.0.2 (Rekap UX & Data)   v1.0.3 (Ops/DX)            v1.1.0 (Value Features)     v1.2.0 (Workflow & Sync)    v1.2.1 (Sync Observability)
┌───────────────────────┐  ┌───────────────────────┐  ┌───────────────────────┐  ┌───────────────────────┐  ┌───────────────────────┐  ┌───────────────────────┐
│ • Tab Guard           │  │ • Table Column Sort   │  │ • Hot-Backup SQLite   │  │ • Tab Konsensus Obat  │  │ • Review KFT Farmasi  │  │ • Test-Webhook CLI    │
│ • Local Draft Storage │  │ • Proposals DB Index  │  │ • Sync Dokter CLI     │  │ • Matriks Multi-DPJP  │  │ • Real-time GS Push   │  │ • Detect 302/401 Auth │
│ • Keyboard Submit     │  │ • OWASP Sanitizer     │  │ • Retention 30 Days   │  │ • Filter Multivariat  │  │ • Apps Script Webhook │  │ • Ping-Pong Secret    │
│ • Counter Karakter    │  │ • Stripping PT/CV     │  │                       │  │ • Accordion Rincian   │  │ • Laravel 12 defer()  │  │ • Failure Reporting   │
└───────────────────────┘  └───────────────────────┘  └───────────────────────┘  └───────────────────────┘  └───────────────────────┘  └───────────────────────┘
```

| Tag Rilis | Fokus Utama | Komponen Fitur | Dampak Utama bagi Rumah Sakit |
| :--- | :--- | :--- | :--- |
| **`v1.0.1`** | Polish Form Mobile | Tab guard, draf lokal, auto-focus, counter 2.000 karakter, action bar mobile | Pengisian obat oleh dokter lebih cepat, bebas risiko kehilangan data saat navigasi. |
| **`v1.0.2`** | UX Rekapitulasi & Hardening | **Interactive Column Ordering**, indeks DB `proposals`, OWASP sanitasi, normalisasi PT/CV | Staf farmasi dapat mengurutkan data langsung di web tanpa repot Excel & data lebih rapi. |
| **`v1.0.3`** | Tooling IT & Backup | Backup atomic SQLite `VACUUM INTO`, command CLI sinkronisasi dokter API | Pemeliharaan berkala basis data aman tanpa lock file WAL & mudah di-cron. |
| **`v1.1.0`** | Nilai Tambah Sidang KFT | Tab konsensus usulan obat ganda, peringkat prioritas multivariat dokter DPJP | Mempercepat sidang formularium tahunan KFT dengan visualisasi obat yang paling banyak didukung. |
| **`v1.2.0`** | Alur Kerja KFT & Realtime Sync | Pencatatan status telaah internal & sinkronisasi Google Sheets realtime (< 2s) via Apps Script + `defer()` | Melacak keputusan hasil sidang KFT dan pembaruan Google Sheets instan tanpa jeda cache. |
| **`v1.2.1`** | Observabilitas & Diagnostik Webhook | Perintah CLI `formulary:test-webhook`, deteksi otomatis 302 login redirect Google & 401 edge auth, ping-pong test, pelaporan failure | Mencegah silent failure sinkronisasi Google Sheets dan mempermudah verifikasi konfigurasi Apps Script. |

---

## 5. Metodologi Rekayasa & Eksekusi: *Two-Tier Specs*

Untuk menjaga konsistensi arsitektur jangka panjang tanpa menimbulkan risiko rencana implementasi menjadi usang (*drift* atau *obsolescence*), proyek ini menerapkan strategi **Two-Tier Specs** dengan memisahkan dokumen desain arsitektur dari dokumen rencana kerja teknis baris per baris.

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ TIER 1: ARCHITECTURAL DESIGN DOCS (UPFRONT BATCH)                                      │
│ Disusun di awal untuk SELURUH tag rilis via skill superpowers:brainstorming             │
│ • v1.0.1 Spec  • v1.0.2 Spec  • v1.0.3 Spec  • v1.1.0 Spec  • v1.2.0 Spec             │
│ Output: docs/superpowers/specs/YYYY-MM-DD-<tag>-<feature>-design.md                   │
│ Fokus: Scope, Boundaries, Anti-Goals, Data Contracts, UX & DB Architectural Guardrails │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼ (Mendasari eksekusi bertahap)
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ TIER 2: JUST-IN-TIME IMPLEMENTATION PLANS (PER TAG RELEASE)                            │
│ Disusun TEPAT SEBELUM eksekusi tag terkait via skill superpowers:writing-plans          │
│ Output: docs/superpowers/plans/YYYY-MM-DD-<tag>-<feature>-plan.md                     │
│ Fokus: Langkah diff file konkret, per-task breakdown, testing matrix, & verification   │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼ (Eksekusi otonom terisolasi)
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ SUBAGENT-DRIVEN DEVELOPMENT (SDD) + SLASH COMMAND /goal                                │
│ Dieksekusi otomatis via skill superpowers:subagent-driven-development                   │
│ • Fresh Implementer Subagent per Task (terisolasi dari token context bloat)            │
│ • Fresh Task Reviewer Subagent per Task (Spec Compliance + Code Quality)               │
│ • Final Whole-Branch Review & Quality Gate Rilis (Pest PHP 100% Pass, Pint, Build Vite)│
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### 5.1 Rincian Pembagian Peran Dokumen
1. **Tier 1 — Spesifikasi Desain Arsitektur (*Design Docs*)**:
   - Disusun dan difinalisasi di muka untuk semua rilis (`v1.0.1` s.d. `v1.2.0`) menggunakan skill `superpowers:brainstorming`.
   - Menetapkan prinsip *what* dan *why*, batasan (*constraints*), apa yang tidak boleh disentuh (*anti-goals*), serta kompatibilitas antar modul.
   - Dokumen ini bersifat stabil dan tidak terpengaruh oleh perubahan internal implementasi tingkat rendah.
2. **Tier 2 — Rencana Eksekusi Kode (*Implementation Plans*)**:
   - Disusun menggunakan skill `superpowers:writing-plans` hanya ketika tag rilis yang bersangkutan siap dikerjakan.
   - Membaca basis kode *terkini* (setelah commit versi sebelumnya selesai dan di-tag), sehingga instruksi baris dan patch diff tidak pernah meleset.

### 5.2 Mekanisme *Fresh Context* Otomatis (Tanpa Membuka Sesi Manual)
- Dalam alur **Subagent-Driven Development (SDD)**, setiap task pengerjaan dan review dipanggil melalui subagent terisolasi (`invoke_subagent`).
- Subagent implementer dan reviewer beroperasi di dalam jendela konteks (*context window*) yang bersih dan terisolasi, hanya menerima instruksi tugas spesifik (*task brief*) tanpa terbebani riwayat percakapan lama.
- Perintah `/goal` menjaga koordinator utama tetap berjalan secara otonom dalam loop multi-turn hingga seluruh task dan verifikasi rilis terselesaikan tanpa perlu intervensi manual.

---

## 6. Prosedur Mutlak Pra-Rilis & Release Tagging (*Quality Gate*)

Sesuai dengan ketentuan baku pada [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md) Bagian 5, setiap rilis tag **WAJIB** memenuhi gerbang mutu berikut sebelum membuat tag:

1. **Working Tree Bersih**: `git status` memastikan tidak ada file terlantar atau uncommitted changes.
2. **Suite Test 100% PASS**: Seluruh unit dan feature test lulus tanpa kegagalan:
   ```bash
   ./vendor/bin/pest
   ```
3. **Standarisasi Kode Bersih**: Standar formatting Laravel Pint terpenuhi:
   ```bash
   ./vendor/bin/pint --test
   ```
4. **Build Frontend Terverifikasi**: Kompilasi Vite + Tailwind CSS v4 + Alpine.js berhasil:
   ```bash
   npm run build
   ```
5. **Annotated Git Tag**: Membuat annotated tag dengan catatan versi:
   ```bash
   git tag -a v1.0.x -m "Release v1.0.x: <Deskripsi Singkat Rilis>"
   git push origin v1.0.x
   ```

---

## 7. Panduan Memulai di Sesi Baru (*Quick-Start Prompt*)

Ketika Anda membuka percakapan/sesi baru untuk memulai penyusunan spesifikasi atau eksekusi, gunakan template instruksi acuan berikut:

### Opsi A: Melanjutkan Penyusunan Spec Tier 1 (Batching Design Docs)
> *"Saya ingin melanjutkan penyusunan spesifikasi desain (Tier 1 Specs) berdasarkan @[ROADMAP.md] menggunakan pendekatan Two-Tier Specs. Saat ini draf spec v1.0.1 sudah ada di `docs/superpowers/specs/2026-11-15-v1-0-1-form-doctor-ux-design.md`. Mari kita tinjau dan finalisasi spec v1.0.1, kemudian lanjutkan menyusun spec untuk v1.0.2 (Interactive Ordering & Hardening) menggunakan skill superpowers:brainstorming."*

### Opsi B: Memulai Implementasi Tag Tertentu (Just-In-Time Plan & SDD)
> *"Berdasarkan spec yang sudah disetujui di @[ROADMAP.md], buatkan implementation plan (Tier 2) untuk tag `v1.0.1` menggunakan skill superpowers:writing-plans. Setelah plan siap dan disetujui, eksekusi implementasi menggunakan superpowers:subagent-driven-development dan /goal."*