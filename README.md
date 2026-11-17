# Sistem Usulan Obat Formularium RS
### RS Aisyiyah Siti Fatimah Tulangan • Komite Farmasi dan Terapi (KFT)

Aplikasi web mandiri (*standalone*) berbasis **Laravel 12**, **Blade**, **Alpine.js**, dan **Tailwind CSS v4** dengan basis data **SQLite WAL Mode** untuk pengumpulan dan penelaahan usulan obat tahunan dari para DPJP (Dokter Penanggung Jawab Pelayanan) tanpa hambatan login (*zero-friction*), dilengkapi dashboard rekapitulasi real-time, analitik konsensus sidang KFT, modul penelaahan farmasi berbasis passcode, integrasi real-time Google Sheets Webhook (< 2 detik) via Laravel 12 `defer()`, serta ekspor spreadsheet berformat siap cetak.

---

## Fitur Utama

- **Zero-Friction Doctor Proposal Form (`/`):**
  - **Bebas Akses (No Auth):** Dokter dapat langsung mengajukan obat tanpa perlu membuat akun atau login PIN.
  - **Searchable Combobox Dokter:** Terhubung langsung ke REST API `portalsifast` dengan sistem fallback bertingkat (Cache 12 Jam $\rightarrow$ API $\rightarrow$ Snapshot JSON Lokal $\rightarrow$ Pengetikan Dokter Manual).
  - **Dynamic Medicine Repeater:** Mengusulkan 1 hingga 50 obat dalam satu kali pengiriman formulir secara atomik.
  - **Self-Learning Autocomplete:** Rekomendasi nama obat & pabrikan menggabungkan kamus referensi RS (~206 obat) dengan riwayat usulan terbaru secara *case-insensitive*.
  - **Principle Auto-Fill:** Memilih obat dari saran otomatis mengisi nama principle/pabrikan pasangannya tanpa mengunci kebebasan dokter.
  - **Pintasan Cepat & UX Mobile:** Auto-save draf formulir lokal (`localStorage`), pemulihan otomatis, pintasan keyboard `Ctrl+Enter` / `Cmd+Enter`, auto-focus baris baru, penghitung kuota 2.000 karakter, dan floating action bar ramah smartphone (`env(safe-area-inset-bottom)` & deteksi virtual keyboard).
  - **Idempotency Lock & Atomic Transaction:** Transaksi master-detail dibungkus `DB::transaction()` dan dilindungi hash SHA-256 di cache selama 15 detik untuk mencegah duplikasi akibat double-click atau retry jaringan lambat.
  - **Safe Form Recovery:** Validasi gagal mengembalikan seluruh baris obat tanpa terpotong berkat isolasi data JSON script carrier.

- **Dashboard Rekapitulasi Real-Time (`/recap`):**
  - **Segmented Dual-Tab Navigation:** Navigasi cepat antara Tab Daftar Usulan (`tab=items`) dan Tab Konsensus Sidang KFT (`tab=consensus`) dengan preservasi filter aktif.
  - **Interactive Multi-Column Ordering:** Pengurutan kolom tabel interaktif (Tanggal, Periode, Dokter, Obat, Principle, Waktu Input) secara ASC/DESC via komponen `<x-table-sort-header>` dengan strict whitelist mapping dan deterministic tie-breaker.
  - **Filter Multi-Dimensi:** Tahun Periode (chip cepat), Status Telaah, Dokter, Principle (menggunakan komponen kustom `<x-custom-select>` dengan navigasi keyboard), Rentang Tanggal, dan Pencarian Bebas.
  - **Paginasi Responsif:** Paginasi 25 baris responsif dengan mempertahankan parameter filter dan sorting.

- **Analitik Konsensus Usulan Obat Sidang KFT (`tab=consensus`):**
  - **Two-Stage Hybrid Aggregation:** Agregasi obat berdasarkan jumlah dukungan DPJP unik (`ProposalConsensusQuery`), menampilkan peringkat usulan terbanyak.
  - **Kartu Metrik KPI Konsensus:** Ringkasan instan Total Obat Diusulkan, Usulan Konsensus (≥ 2 Dokter), dan Rekor Dukungan Tertinggi.
  - **Accordion Komparasi DPJP:** Penelusuran rincian perbandingan klinis antar-dokter, spesialisasi/klinik, variasi principle, serta alasan justifikasi medis dalam kartu interaktif.
  - **Quick Filter Chip:** Opsi 1-klik untuk memfilter *"Semua Usulan"* vs *"Hanya Usulan Konsensus (≥ 2 Dokter)"*.

- **Modul Telaah KFT Farmasi:**
  - **Otorisasi Sesi Berbasis Passcode:** Akses telaah diamankan dengan shared passcode (`FORMULARY_REVIEW_SECRET`) dan proteksi rate limiter `throttle:10,1` tanpa beban manajemen akun pengguna yang rumit.
  - **Mutasi Status & Catatan Telaah:** Apoteker dapat menetapkan status telaah (*Pending*, *Disetujui*, *Ditolak*, atau *Substitusi Formularium*) beserta catatan pertimbangan KFT melalui modal interaktif inline.
  - **Transparansi Klinis Terbuka:** Seluruh dokter dan staf dapat melihat badge status telaah dan catatan pertimbangan KFT secara transparan di dashboard tanpa perlu login.

- **Integrasi Spreadsheet & Real-Time Webhook:**
  - **Real-Time Push ke Google Sheets (< 2 Detik):** Memanfaatkan Laravel 12 `defer()` pada Controller untuk memicu HTTP POST 12 kolom kanonikal ke Webhook Google Apps Script secara instan pasca-transaksi tanpa membebani waktu tunggu dokter.
  - **Tooling Diagnostik Webhook (`formulary:test-webhook`):** Pengujian otomatis ketersediaan endpoint, deteksi isu hak akses Google Web App (302 redirect login / 401 unauthorized), serta pengujian respon ping-pong shared secret.
  - **Bulk Reconciliation CLI (`formulary:sync-sheets`):** Perintah sinkronisasi massal seluruh data historis ke Google Sheets dengan opsi pembersihan lembar kerja (`--clear`) dan filter tahun (`--year`).
  - **Live CSV Feed Ber-Token (Safeguard 8-Kolom vs 12-Kolom Opt-in):** URL CSV feed publik diamankan dengan `hash_equals()`. Kontrak default 8 kolom lama tetap dipertahankan untuk mencegah error `#REF!` formula spill di spreadsheet yang sudah berjalan, sementara 12 kolom kanonikal dibuka via parameter opt-in `&include_review=1`.
  - **Unduh Excel Berformat (`.xlsx`):** Dibuat menggunakan engine ringan `openspout/openspout` dengan streaming memori rendah (< 10 MB), header hijau zamrud RS (`#065F46`), teks tebal putih, dan border terukur rapi.
  - **Anti-Formula Injection OWASP:** Sanitasi otomatis karakter formula berbahaya (`=`, `+`, `-`, `@`, `|`, `%`) dengan pemotongan leading whitespace.

- **Tooling Operasional & Otomatisasi IT:**
  - **Hot-Backup Atomic SQLite:** Perintah `formulary:backup` menggunakan query native SQLite `VACUUM INTO` untuk pencadangan konsisten tanpa risiko file lock WAL, lengkap dengan rotasi otomatis 30 hari.
  - **Sinkronisasi Direktori Dokter Otomatis:** Perintah `formulary:sync-doctors` untuk memperbarui cache dan snapshot direktori dokter Portal Sifast secara terjadwal (pukul 06:00 & 18:00 WIB).
  - **Normalisasi Principle Historis:** Perintah `formulary:normalize-principals` untuk membersihkan awalan badan usaha (PT/CV) dan pemetaan alias pabrikan secara aman dengan dukungan mode simulasi `--dry-run`.

---

## Tech Stack & Arsitektur

| Lapisan | Teknologi | Keterangan |
| :--- | :--- | :--- |
| **Backend** | PHP `^8.3`, Laravel `^12.0` | Monolitik fungsional ringan, routing modern `bootstrap/app.php`, deferred tasks |
| **Database** | SQLite 3 (`WAL` Mode) | `busy_timeout = 5000`, `synchronous = NORMAL`, `foreign_keys = true`, hot-backup `VACUUM INTO` |
| **Frontend** | Blade + Alpine.js `^3.17` | Dibundel lokal via Vite (tanpa CDN eksternal), arsitektur modular komponen |
| **Styling** | Tailwind CSS `^4.0` | Terintegrasi via `@tailwindcss/vite` dengan palet hijau zamrud RS (`#065F46`) |
| **Spreadsheet**| `openspout/openspout` `^4.32` | Streaming memory-efficient Excel XLSX export (< 10 MB) |
| **Testing** | Pest PHP `^3.7` | **88 Feature & Unit Tests (510 assertions, 100% pass)** |
| **Code Style** | Laravel Pint `^1.24` | Strict types `declare(strict_types=1);` pada seluruh berkas PHP |

Dokumentasi arsitektur dan teknis mendalam tersedia di [docs/DOKUMENTASI_SISTEM.md](docs/DOKUMENTASI_SISTEM.md).

---

## Panduan Cepat Memulai (*Quick Start*)

### 1. Kebutuhan Sistem
- PHP `>= 8.3` (ekstensi: `pdo_sqlite`, `curl`, `mbstring`, `zip`, `xml`, `openssl`, `tokenizer`)
- Composer `>= 2.7`
- Node.js `>= 20.0` & NPM
- SQLite 3

### 2. Instalasi & Setup Lingkungan
```bash
# Clone atau buka direktori proyek
cd FormulariumRS

# Pasang dependensi
composer install
npm install

# Setup konfigurasi lingkungan
cp .env.example .env
php artisan key:generate

# Buat database SQLite dan jalankan migrasi
touch database/database.sqlite
php artisan migrate

# Isi database dengan kamus obat & simulasi dokter API
php artisan db:seed --class=FormulariumDataSeeder

# Kompilasi aset Vite untuk produksi
npm run build
```

### 3. Konfigurasi Penting `.env`
Pastikan variabel berikut terisi di berkas `.env`:
```dotenv
APP_NAME="Formularium RS"
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=sqlite
DB_FOREIGN_KEYS=true

# API Direktori Dokter Portal Sifast RS
FORMULARY_DOCTOR_API_URL="https://portalsifast.rsaisyiyahsitifatimah.com/api/dokter"
FORMULARY_DOCTOR_API_TIMEOUT=10
FORMULARY_DOCTOR_CACHE_TTL_HOURS=12

# Token 32 karakter untuk mengamankan Live CSV Feed Spreadsheet
FORMULARY_FEED_TOKEN="ganti_dengan_token_rahasia_32_karakter"

# Konfigurasi Backup Otomatis SQLite
FORMULARY_BACKUP_PATH="./storage/app/backups"
FORMULARY_BACKUP_RETENTION_DAYS=30

# Passcode Sesi Telaah KFT Farmasi
FORMULARY_REVIEW_SECRET="passcode_kft_rahasia_anda"

# Integrasi Real-Time Google Apps Script Webhook
FORMULARY_SHEET_WEBHOOK_URL="https://script.google.com/macros/s/.../exec"
FORMULARY_SHEET_WEBHOOK_SECRET="webhook_secret_token_anda"
```

> **Cara Mengenerate `FORMULARY_FEED_TOKEN`:**
> ```bash
> php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"
> ```

### 4. Menjalankan Server Pengembangan Lokal
```bash
composer run dev
```
Akses di browser:
- Formulir Usulan Dokter: `http://localhost:8000/`
- Rekapitulasi & Telaah KFT: `http://localhost:8000/recap`

### 5. Membagikan Akses Publik via Cloudflare Tunnel
Untuk pengujian mobile oleh para DPJP:
- **Windows (Herd)**: Jalankan `.\tunnel.bat`
- **macOS (Terminal)**: Jalankan `./tunnel.sh`

Panduan lengkap konfigurasi tunnel tersedia di [docs/PANDUAN_TUNNEL.md](docs/PANDUAN_TUNNEL.md).

---

## Perintah Artisan yang Tersedia

Aplikasi dilengkapi perintah CLI khusus untuk pemeliharaan dan pengoperasian sistem:

```bash
# 1. Menguji ketersediaan, hak akses Google Web App, dan secret webhook
php artisan formulary:test-webhook

# 2. Sinkronisasi & rekonsiliasi massal ke Google Sheets Webhook
php artisan formulary:sync-sheets --clear

# 3. Hot-Backup atomic basis data SQLite (Aman WAL, rotasi 30 hari)
php artisan formulary:backup

# 4. Sinkronisasi paksa direktori dokter dari REST API Portal Sifast
php artisan formulary:sync-doctors

# 5. Normalisasi nama principle historis (pembersihan PT/CV dan alias)
php artisan formulary:normalize-principals --dry-run
php artisan formulary:normalize-principals

# 6. Mengimpor atau memperbarui kamus CSV referensi obat & pabrikan
php artisan formulary:import-csv database/seeders/data/formulary_medicines.csv

# 7. Menjalankan seluruh pengujian otomatis (88 tests, 510 assertions)
./vendor/bin/pest

# 8. Memeriksa kepatuhan formatting kode PHP
./vendor/bin/pint --test
```

---

## Struktur Direktori Utama

```text
FormulariumRS/
├── app/
│   ├── Console/Commands/
│   │   ├── DatabaseBackupCommand.php         # formulary:backup (SQLite VACUUM INTO)
│   │   ├── ImportFormularyCsvCommand.php     # formulary:import-csv (kamus referensi obat)
│   │   ├── NormalizePrincipalsCommand.php    # formulary:normalize-principals (PT/CV strip)
│   │   ├── SyncDoctorsCommand.php            # formulary:sync-doctors (API Portal Sifast)
│   │   ├── SyncGoogleSheetCommand.php        # formulary:sync-sheets (bulk webhook push)
│   │   └── TestGoogleSheetWebhookCommand.php # formulary:test-webhook (diagnostik & ping)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ProposalController.php        # Form usulan, store atomik & defer push
│   │   │   ├── ProposalRecapController.php   # Dashboard rekapitulasi & konsensus
│   │   │   ├── ProposalReviewController.php  # Autentikasi passcode & telaah KFT
│   │   │   ├── ProposalExportController.php  # Live CSV feed 8/12 col & OpenSpout XLSX
│   │   │   └── SuggestionController.php      # Autocomplete JSON dokter, obat, principle
│   │   └── Requests/
│   │       └── StoreProposalRequest.php      # Validasi & normalisasi teks
│   ├── Models/
│   │   ├── Proposal.php                      # Model master sesi usulan DPJP
│   │   ├── ProposalItem.php                  # Model rincian obat & kolom telaah KFT
│   │   └── ReferenceMedicine.php             # Kamus obat referensi RS
│   ├── Queries/
│   │   ├── ProposalItemRecapQuery.php        # Filter rekapitulasi & whitelist sorter
│   │   └── ProposalConsensusQuery.php        # Agregasi hibrida 2-tahap konsensus KFT
│   ├── Services/
│   │   ├── DoctorDirectoryService.php        # Multi-tier direktori dokter Portal Sifast
│   │   ├── AutocompleteService.php           # Logika hibrida saran obat & principle
│   │   ├── GoogleSheetSyncService.php        # Real-time webhook push & bulk sync
│   │   ├── FormularyCsvImporter.php          # Parser CSV RFC-4180 multi-baris
│   │   └── ProposalExportService.php         # Penulis spreadsheet XLSX OpenSpout
│   └── Support/
│       ├── TextNormalizer.php                # Normalisasi spasi, strip PT/CV & alias
│       └── SpreadsheetSanitizer.php          # Sanitasi CSV/XLSX formula injection OWASP
├── config/
│   ├── database.php                          # Konfigurasi SQLite WAL & timeout 5000
│   └── formulary.php                         # Pengaturan API dokter, backup, KFT & sheet
├── database/
│   ├── migrations/                           # Migrasi proposals, items, & reference_medicines
│   └── seeders/                              # Seeder kamus obat dan data simulasi
├── docs/
│   ├── DOKUMENTASI_SISTEM.md                 # Dokumentasi teknis & arsitektur komprehensif
│   ├── PANDUAN_TUNNEL.md                     # Panduan konfigurasi Cloudflare Tunnel
│   └── superpowers/                          # Arsip spesifikasi arsitektur & rencana kerja
├── resources/
│   ├── css/app.css                           # Styling Tailwind CSS v4 palet hijau zamrud RS
│   ├── js/app.js                             # Alpine.js debounced autocomplete & repeater
│   ├── scripts/
│   │   └── google-apps-script-webhook.js     # Template resmi Webhook Google Apps Script
│   └── views/
│       ├── components/                       # Layout shell & Blade components (<x-custom-select>, <x-table-sort-header>)
│       └── proposals/
│           ├── create.blade.php              # View formulir usulan obat dokter
│           ├── index.blade.php               # View dashboard rekapitulasi & modal telaah
│           └── partials/
│               └── consensus-tab.blade.php   # Partial tab analitik konsensus sidang KFT
└── tests/
    ├── Feature/                              # Pengujian integrasi submission, export, filter, kft, sheet
    └── Unit/                                 # Pengujian normalisasi teks & parser CSV
```

---

## Lisensi & Kepemilikan
Aplikasi ini dikembangkan khusus untuk **RS Aisyiyah Siti Fatimah Tulangan** di bawah pengelolaan Komite Farmasi dan Terapi (KFT) & Instalasi Farmasi.  
Hak Cipta &copy; 2026 RS Aisyiyah Siti Fatimah Tulangan. Seluruh hak cipta dilindungi.
