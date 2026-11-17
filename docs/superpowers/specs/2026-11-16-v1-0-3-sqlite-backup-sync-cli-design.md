# Design Document: FormulariumRS v1.0.3 — Hot-Backup Atomic SQLite & CLI Sync Dokter

**Tanggal:** 2026-11-16  
**Target Rilis:** `v1.0.3`  
**Status:** Disetujui (Builder Review Passed: Ready for Tier 2 Implementation Plan)  
**Dokumen Terkait:** [ROADMAP.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/ROADMAP.md), [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md)

---

## 1. Intent & The Why

Aplikasi **FormulariumRS** beroperasi di lingkungan produksi menggunakan basis data **SQLite 3** dengan mode *Write-Ahead Logging* (`WAL`) untuk menangani konkurensi dokter di RS Aisyiyah Siti Fatimah Tulangan.

Terdapat dua kebutuhan operasional IT yang mendesak:
1. **Risiko Korupsi Data saat Backup Tradisional:** Menyalin berkas SQLite secara manual (`cp database.sqlite`) ketika sistem aktif sangat berisiko menghasilkan salinan yang korup (*corrupt database image*) karena transaksi terbaru masih berada di berkas `-wal` dan `-shm`. SQLite menyediakan perintah bawaan `VACUUM INTO 'path/to/backup.sqlite'` yang mengeksekusi *atomic point-in-time snapshot* secara konsisten tanpa menghentikan atau mengunci transaksi baca/tulis yang sedang berlangsung.
2. **Ketergantungan Sinkronisasi Data Dokter pada Interaksi Pengguna:** Saat ini pembaruan master dokter dari API Portal Sifast hanya terjadi jika dokter membuka formulir usulan tepat setelah masa kedaluwarsa cache (12 jam) habis. Tim IT membutuhkan sarana CLI mandiri yang dapat dijalankan terjadwal via cron atau dipanggil manual saat ada penambahan dokter spesialis baru di rumah sakit.

---

## 2. Goals, Non-Goals, and Anti-Goals

### Goals
- **Artisan Hot-Backup (`php artisan formulary:backup`):**
  - Menggunakan engine bawaan `VACUUM INTO` untuk menghasilkan berkas basis data tunggal murni (`.sqlite`) yang langsung dapat digunakan/diinspeksi.
  - Menyimpan berkas di `storage/app/backups/formulary_YYYY-MM-DD_His.sqlite`.
  - Rotasi retensi otomatis dengan menghapus berkas backup yang berumur lebih dari 30 hari (dapat dikustomisasi via opsi `--retention=N` dan `config/formulary.php`).
  - Umpan balik CLI informatif: lokasi berkas, ukuran file (KB/MB), durasi proses (ms), dan jumlah file lama yang dibersihkan.
- **Artisan Sync Dokter (`php artisan formulary:sync-doctors`):**
  - Menyediakan public method `refreshFromApi(): array` pada `DoctorDirectoryService`.
  - Selalu melakukan sinkronisasi langsung ke API secara *unconditional* (tanpa memerlukan flag) untuk menjamin pembaruan data dokter secara aktif.
  - Memperbarui berkas cadangan lokal `storage/app/doctors_snapshot.json` dan memperbarui cache Laravel.
  - Penanganan error yang elegan: jika API Portal Sifast sedang *down*, proses tidak me-reset snapshot lama menjadi kosong, mencatat log peringatan dengan jelas, dan mengembalikan exit code 1.
- **Automated Scheduling (`routes/console.php`):**
  - Mendaftarkan cron job terintegrasi Laravel Schedule untuk backup harian (`dailyAt('02:00')`) dan sinkronisasi dokter dua kali sehari (`twiceDaily(6, 18)`).

### Non-Goals
- Tidak mengunggah berkas backup ke penyimpanan cloud pihak ketiga (S3/Google Drive) pada rilis ini; penyimpanan tetap berada di filesystem lokal server.
- Tidak melakukan kompresi Gzip (`.gz`) pada berkas backup (sesuai kesepakatan Opsi A: Raw SQLite murni agar mudah diinspeksi langsung oleh IT tanpa dekompresi).
- Tidak memodifikasi tabel database atau skema migrasi apa pun.

### Anti-Goals
- **Dilarang menggunakan `copy()` / `File::copy()`** pada berkas `database.sqlite` yang aktif.
- **Dilarang menghapus berkas non-backup** saat rotasi retensi berlangsung (hanya memindai berkas dengan pola nama `formulary_*.sqlite` di folder backup).
- **Dilarang mengosongkan berkas `doctors_snapshot.json`** jika koneksi ke API Portal Sifast mengalami timeout atau error HTTP 5xx.

---

## 3. Constraints

- **Prinsip AGENTS.md:**
  - Wajib `declare(strict_types=1);` di setiap berkas PHP baru.
  - Driver basis data wajib SQLite (`config('database.default') === 'sqlite'`).
  - Pest test suite (`./vendor/bin/pest`) wajib 100% PASS.
  - Formatting Laravel Pint (`./vendor/bin/pint --test`) wajib bersih.

---

## 4. Approaches

### Opsi yang Dipertimbangkan:
1. **Opsi 1: Dedicated Artisan Command Classes + Thin Service Methods (Dipilih)**
   - Membuat `DatabaseBackupCommand.php` dan `SyncDoctorsCommand.php`.
   - Menambahkan public method `refreshFromApi()` di `DoctorDirectoryService.php`.
   - Penjadwalan deklaratif di `routes/console.php`.
   - *Alasan Pemilihan:* Arsitektur modular, mudah diuji via Pest PHP (`$this->artisan(...)->assertSuccessful()`), dan mematuhi konvensi Laravel 12.

2. **Opsi 2: Closure-Based Commands di `routes/console.php`**
   - Menulis seluruh logika di dalam closure route console.
   - *Alasan Ditolak:* Mencampur deklarasi jadwal dengan manipulasi IO filesystem dan query database, menyulitkan unit testing.

3. **Opsi 3: External OS Shell Script (Bash / Cron)**
   - Script shell di luar Laravel.
   - *Alasan Ditolak:* Mengabaikan environment Laravel dan sulit dipelihara lintas OS.

---

## 5. Detailed Architecture & Design Decisions

### 5.1 Konfigurasi Aplikasi (`config/formulary.php`)
Menambahkan parameter konfigurasi backup:
```php
return [
    'doctor_api_url' => env('FORMULARY_DOCTOR_API_URL', 'https://portalsifast.rsaisyiyahsitifatimah.com/api/dokter'),
    'doctor_api_timeout' => (int) env('FORMULARY_DOCTOR_API_TIMEOUT', 10),
    'doctor_cache_ttl_hours' => (int) env('FORMULARY_DOCTOR_CACHE_TTL_HOURS', 12),
    'feed_token' => env('FORMULARY_FEED_TOKEN', ''),
    'backup_path' => env('FORMULARY_BACKUP_PATH', storage_path('app/backups')),
    'backup_retention_days' => (int) env('FORMULARY_BACKUP_RETENTION_DAYS', 30),
];
```

### 5.2 Command Database Backup (`app/Console/Commands/DatabaseBackupCommand.php`)

- **Signature:** `formulary:backup {--retention= : Jumlah hari retensi penyimpanan berkas backup}`
- **Deskripsi:** *"Membuat atomic point-in-time snapshot database SQLite menggunakan VACUUM INTO dan melakukan rotasi retensi."*
- **Alur Kerja:**
  1. **Validasi Koneksi:** Memastikan koneksi database saat ini bertipe `sqlite`. Jika bukan, tampilkan error dan gagalkan dengan exit code 1.
  2. **Persiapan Direktori:** Pastikan direktori tujuan (`config('formulary.backup_path')`) sudah ada via `File::ensureDirectoryExists()`.
  3. **Penamaan File & Penanganan Tabrakan:** `formulary_Y-m-d_His.sqlite` (misal: `formulary_2026-11-16_020000.sqlite`). Jika berkas dengan nama tersebut telah ada, hapus terlebih dahulu via `File::delete($targetPath)` agar SQLite tidak menolak operasi `VACUUM INTO`.
  4. **Eksekusi Atomik:**
     ```php
     $startTime = microtime(true);
     DB::statement('VACUUM INTO ?', [$targetPath]);
     $duration = round((microtime(true) - $startTime) * 1000, 2);
     ```
  5. **Rotasi Retensi:**
     - Ambil batas hari dari opsi CLI `--retention` (atau fallback ke `config('formulary.backup_retention_days')`), dengan nilai aman `$retention = max(1, (int) $rawRetention)`.
     - Hitung cutoff time: `$cutoff = now()->subDays($retention)->getTimestamp()`.
     - Pindai seluruh file yang cocok dengan pola `formulary_*.sqlite` di dalam direktori backup.
     - Hapus berkas yang memiliki `filemtime($file) < $cutoff`.
  6. **Pelaporan CLI:**
     - Tampilkan ukuran berkas (`File::size($targetPath)` yang diformat KB/MB).
     - Tampilkan durasi eksekusi dan jumlah file yang dibersihkan.
     - Kembalikan `Command::SUCCESS`.

### 5.3 Pembaruan `DoctorDirectoryService` (`app/Services/DoctorDirectoryService.php`)

1. **Public Method `refreshFromApi(): array`:**
   - Menghapus cache existing: `Cache::forget(self::CACHE_KEY)`.
   - Mengambil data dari API Portal Sifast menggunakan logic HTTP client yang ada (dengan timeout dan retry).
   - Jika response berhasil dan data valid:
     - Simpan data ke `storage/app/doctors_snapshot.json`.
     - Simpan ke Cache Laravel dengan durasi TTL (default 12 jam).
     - Mengembalikan data dokter aktif `list<array{name: string, clinic: ?string}>`.
   - Jika gagal (koneksi putus/timeout):
     - Catat log warning: `Log::warning('Sinkronisasi dokter dari API gagal: ...')`.
     - Jangan menimpa file `doctors_snapshot.json` dengan data kosong.
     - Lemparkan `RuntimeException` khusus atau kembalikan status kegagalan yang dapat ditangkap oleh command CLI.
2. **Refactor `resolveBaseDoctors()`:**
   - Memanfaatkan sub-method privat yang reusable sehingga pemanggilan otomatis dari web request tetap berjalan mulus menggunakan alur Cache ➔ API ➔ Snapshot.

### 5.4 Command Sync Dokter (`app/Console/Commands/SyncDoctorsCommand.php`)

- **Signature:** `formulary:sync-doctors`
- **Deskripsi:** *"Menyinkronkan master dokter aktif dari API Portal Sifast ke cache dan snapshot lokal."*
- **Alur Kerja:**
  1. Injeksi instans `DoctorDirectoryService`.
  2. Panggil `refreshFromApi()` secara *unconditional* (selalu memperbarui data dari API langsung).
  3. Jika sukses:
     - Tampilkan ringkasan: jumlah dokter aktif yang berhasil disinkronkan.
     - Tampilkan tabel pratinjau 5 dokter pertama.
     - Kembalikan `Command::SUCCESS`.
  4. Jika gagal:
     - Tampilkan error message: *"Gagal menghubungi API Portal Sifast. Berkas snapshot lokal tetap dipertahankan."*
     - Kembalikan `Command::FAILURE`.

### 5.5 Penjadwalan Otomatis (`routes/console.php`)

Mendaftarkan jadwal otomatis via Laravel 12 Scheduler:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('formulary:backup')
    ->dailyAt('02:00')
    ->runInBackground();

Schedule::command('formulary:sync-doctors')
    ->twiceDaily(6, 18)
    ->runInBackground();
```

---

## 6. What's Left to the Builder

- Implementasi class `DatabaseBackupCommand` dan `SyncDoctorsCommand`.
- Penulisan unit/feature test komprehensif di Pest PHP:
  - `DatabaseBackupCommandTest`: Menguji pembuatan file backup SQLite asli, verifikasi ukuran berkas > 0, pengujian rotasi retensi (simulasi file berusia > 30 hari yang otomatis terhapus), dan penolakan driver non-sqlite.
  - `SyncDoctorsCommandTest`: Menguji skenario HTTP mock sukses (verifikasi snapshot dan cache terbarui) dan skenario HTTP mock gagal (verifikasi snapshot tidak korup dan command return failure).
- Registrasi penjadwalan di `routes/console.php`.
- Menjalankan `./vendor/bin/pest` untuk memastikan 100% lulus.
- Menjalankan `./vendor/bin/pint --test` untuk memastikan standarisasi kode PHP.
