# FormulariumRS v1.0.3 Implementation Plan: Hot-Backup Atomic SQLite & CLI Sync Dokter

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghadirkan command Artisan pencadangan basis data berkala yang atomik (`php artisan formulary:backup`) berbasis `VACUUM INTO` dengan rotasi retensi 30 hari, command sinkronisasi direktori dokter (`php artisan formulary:sync-doctors`) dengan proteksi snapshot lokal, serta penjadwalan otomatis di `routes/console.php`.

**Architecture:** Operasional pencadangan memanfaatkan engine bawaan SQLite `VACUUM INTO` untuk membuat snapshot tunggal yang konsisten tanpa lock transaksi tabel WAL. `DoctorDirectoryService` diperluas dengan public method `refreshFromApi(): array` yang melakukan sinkronisasi langsung dan atomik ke API Portal Sifast tanpa merusak snapshot lokal saat API mengalami gangguan. Kedua command didaftarkan pada scheduler Laravel (`routes/console.php`) dengan `runInBackground()`.

**Tech Stack:** PHP `^8.3`, Laravel `^12.0`, SQLite 3 (`VACUUM INTO`), Laravel Scheduler, Pest PHP `^3.7`, Laravel Pint `^1.24`.

**Spec:** [docs/superpowers/specs/2026-11-16-v1-0-3-sqlite-backup-sync-cli-design.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/docs/superpowers/specs/2026-11-16-v1-0-3-sqlite-backup-sync-cli-design.md)

## Global Constraints

- Wajib mematuhi pedoman teknis [AGENTS.md](file:///Users/adijaya/MyFiles/Projects/RSAisyiahSitiFatimahTulangan/FormulariumRS/AGENTS.md): `declare(strict_types=1);` di setiap berkas PHP baru/dimodifikasi, tanpa generic repository pattern.
- Dilarang keras menggunakan `File::copy()` atau `copy()` pada berkas `database.sqlite` aktif (wajib melalui `DB::statement('VACUUM INTO ?', [$path])`).
- Dilarang mengosongkan berkas snapshot `storage/app/doctors_snapshot.json` saat API gagal dihubungi.
- Seluruh pengujian Pest PHP (`./vendor/bin/pest`) wajib 100% PASS (minimal 41 tests baseline).
- Standarisasi formatting Laravel Pint (`./vendor/bin/pint --test`) wajib bersih tanpa error.

## Review Focus

1. **Tabrakan File Target pada `VACUUM INTO`:** SQLite melempar error jika berkas tujuan sudah ada. Command `DatabaseBackupCommand` wajib memastikan berkas dengan nama yang sama dihapus terlebih dahulu jika sudah ada sebelum `VACUUM INTO` dieksekusi. *(Diuji di Task 2).*
2. **Proteksi Snapshot Dokter saat API Down:** Ketika endpoint Portal Sifast mengembalikan HTTP 500 atau timeout, `refreshFromApi()` dan `SyncDoctorsCommand` wajib menjaga keutuhan `doctors_snapshot.json` yang lama dan mengembalikan `Command::FAILURE`. *(Diuji di Task 1 & Task 3).*
3. **Pembatasan Batas Rotasi Retensi File Backup:** Pembersihan file kadaluwarsa hanya memindai berkas yang berformat `formulary_*.sqlite` di dalam direktori backup, dan tidak menghapus berkas lain. *(Diuji di Task 2).*
4. **Validasi Driver SQLite:** `DatabaseBackupCommand` wajib mengecek `DB::getDriverName() === 'sqlite'` dan menolak eksekusi pada koneksi non-sqlite. *(Diuji di Task 2).*
5. **Eksekusi Background pada Scheduler:** Penjadwalan pada `routes/console.php` wajib menggunakan `->runInBackground()` agar operasi IO backup atau network sync tidak memblokir antrean schedule daemon. *(Diuji di Task 4).*

---

### Task 1: Configuration & `DoctorDirectoryService::refreshFromApi()`

**Files:**
- Modify: `config/formulary.php`
- Modify: `app/Services/DoctorDirectoryService.php`
- Modify: `tests/Feature/DoctorDirectoryServiceTest.php`

**Interfaces:**
- Consumes: `config('formulary.doctor_api_url')`, API Portal Sifast
- Produces:
  - `config('formulary.backup_path')` dan `config('formulary.backup_retention_days')`
  - Public method `DoctorDirectoryService::refreshFromApi(): array` yang me-refresh cache dan snapshot lokal, serta melempar `\RuntimeException` jika API gagal tanpa merusak snapshot lama.

- [ ] **Step 1: Write the failing test**

Tambahkan pengujian baru di `tests/Feature/DoctorDirectoryServiceTest.php`:
```php
it('refreshes doctors from api unconditionally, updates snapshot and cache, and preserves snapshot on failure', function (): void {
    $service = app(DoctorDirectoryService::class);

    // 1. Successful refresh
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [[
                'kdDokter' => '999',
                'namaDokter' => 'dr. Dokter Baru Sp.PD',
                'isAktif' => true,
                'poli' => [['kdPoli' => 'INT', 'namaPoli' => 'Klinik Penyakit Dalam']],
            ]],
        ], 200),
    ]);

    $result = $service->refreshFromApi();
    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('dr. Dokter Baru Sp.PD');

    // 2. Failure preserves existing snapshot and throws exception
    Http::fake([
        '*' => Http::response([], 500),
    ]);

    expect(fn () => $service->refreshFromApi())->toThrow(RuntimeException::class);
    expect($service->listDoctors('Dokter Baru'))->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/DoctorDirectoryServiceTest.php`
Expected: FAIL (`Call to undefined method ... refreshFromApi()`).

- [ ] **Step 3: Update `config/formulary.php` & implement `refreshFromApi()`**

1. Di `config/formulary.php`:
   ```php
   'backup_path' => env('FORMULARY_BACKUP_PATH', storage_path('app/backups')),
   'backup_retention_days' => (int) env('FORMULARY_BACKUP_RETENTION_DAYS', 30),
   ```
2. Di `app/Services/DoctorDirectoryService.php`:
   - Implementasikan method `public function refreshFromApi(): array`:
     - Flush cache: `Cache::forget(self::CACHE_KEY);`
     - Panggil API dengan timeout.
     - Jika respon sukses dan data valid: simpan ke `doctors_snapshot.json` dan simpan ke Cache, lalu return list.
     - Jika gagal: log warning dan lempar `new \RuntimeException('Gagal menyinkronkan data dokter dari API Portal Sifast.');` tanpa memodifikasi berkas snapshot.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/DoctorDirectoryServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/formulary.php app/Services/DoctorDirectoryService.php tests/Feature/DoctorDirectoryServiceTest.php
git commit -m "feat(services): implement DoctorDirectoryService refreshFromApi and add backup config"
```

---

### Task 2: Artisan Command `formulary:backup` with `VACUUM INTO` and Retention

**Files:**
- Create: `app/Console/Commands/DatabaseBackupCommand.php`
- Create: `tests/Feature/DatabaseBackupCommandTest.php`

**Interfaces:**
- Consumes: `DB::connection()`, `config('formulary.backup_path')`, `config('formulary.backup_retention_days')`
- Produces: CLI command `php artisan formulary:backup {--retention=}` yang menghasilkan file snapshot `.sqlite` menggunakan `VACUUM INTO` dan merotasi file lebih tua dari batas retensi.

- [ ] **Step 1: Write the failing test**

Buat `tests/Feature/DatabaseBackupCommandTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('creates an atomic sqlite backup file using vacuum into and rotates old backups', function (): void {
    $backupDir = storage_path('framework/testing/backups');
    config(['formulary.backup_path' => $backupDir]);
    File::ensureDirectoryExists($backupDir);
    File::cleanDirectory($backupDir);

    // Create a mock stale backup file older than 30 days
    $oldFile = $backupDir.'/formulary_2020-01-01_000000.sqlite';
    file_put_contents($oldFile, 'dummy stale sqlite');
    touch($oldFile, now()->subDays(35)->getTimestamp());

    $this->artisan('formulary:backup', ['--retention' => 30])
        ->expectsOutputToContain('Backup basis data berhasil')
        ->assertSuccessful();

    $backups = File::glob($backupDir.'/formulary_*.sqlite');
    expect($backups)->toHaveCount(1)
        ->and(file_exists($oldFile))->toBeFalse();

    File::deleteDirectory($backupDir);
});

it('rejects backup execution when database driver is not sqlite', function (): void {
    config(['database.default' => 'mysql']);

    $this->artisan('formulary:backup')
        ->expectsOutputToContain('hanya mendukung driver SQLite')
        ->assertFailed();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/DatabaseBackupCommandTest.php`
Expected: FAIL (`Command "formulary:backup" is not defined`).

- [ ] **Step 3: Implement `DatabaseBackupCommand.php`**

Buat `app/Console/Commands/DatabaseBackupCommand.php`:
- Signature: `formulary:backup {--retention= : Jumlah hari retensi penyimpanan berkas backup}`
- Validasi `DB::getDriverName() === 'sqlite'`. Jika tidak, return `self::FAILURE`.
- Pastikan folder tujuan ada via `File::ensureDirectoryExists($backupPath)`.
- Tentukan filename: `formulary_'.now()->format('Y-m-d_His').'.sqlite'`.
- Hapus file target jika sudah ada (`File::delete($target)`).
- Eksekusi: `DB::statement('VACUUM INTO ?', [$target]);`.
- Bersihkan file lama berumur > `$retention` hari yang cocok dengan pola `formulary_*.sqlite`.
- Tampilkan statistik ukuran dan durasi, return `self::SUCCESS`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/DatabaseBackupCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/DatabaseBackupCommand.php tests/Feature/DatabaseBackupCommandTest.php
git commit -m "feat(cli): add formulary:backup command using VACUUM INTO and retention rotation"
```

---

### Task 3: Artisan Command `formulary:sync-doctors`

**Files:**
- Create: `app/Console/Commands/SyncDoctorsCommand.php`
- Create: `tests/Feature/SyncDoctorsCommandTest.php`

**Interfaces:**
- Consumes: `DoctorDirectoryService::refreshFromApi()`
- Produces: CLI command `php artisan formulary:sync-doctors`

- [ ] **Step 1: Write the failing test**

Buat `tests/Feature/SyncDoctorsCommandTest.php`:
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

it('synchronizes doctors from api and displays preview table on success', function (): void {
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [[
                'kdDokter' => '101',
                'namaDokter' => 'dr. Zaidan Sp.A',
                'isAktif' => true,
                'poli' => [['kdPoli' => 'ANA', 'namaPoli' => 'Klinik Anak']],
            ]],
        ], 200),
    ]);

    $this->artisan('formulary:sync-doctors')
        ->expectsOutputToContain('Sinkronisasi data dokter berhasil')
        ->assertSuccessful();
});

it('handles api failure gracefully without corrupting local snapshot and exits with failure', function (): void {
    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $this->artisan('formulary:sync-doctors')
        ->expectsOutputToContain('Gagal menghubungi API Portal Sifast')
        ->assertFailed();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/SyncDoctorsCommandTest.php`
Expected: FAIL (`Command "formulary:sync-doctors" is not defined`).

- [ ] **Step 3: Implement `SyncDoctorsCommand.php`**

Buat `app/Console/Commands/SyncDoctorsCommand.php`:
- Signature: `formulary:sync-doctors`
- Panggil `DoctorDirectoryService::refreshFromApi()`.
- Jika sukses: tampilkan pesan sukses, tabel 5 dokter pertama, return `self::SUCCESS`.
- Jika menangkap exception: tampilkan error bahwa snapshot lokal dipertahankan, return `self::FAILURE`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/SyncDoctorsCommandTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SyncDoctorsCommand.php tests/Feature/SyncDoctorsCommandTest.php
git commit -m "feat(cli): add formulary:sync-doctors command"
```

---

### Task 4: Automated Scheduling & Quality Gate for `v1.0.3`

**Files:**
- Modify: `routes/console.php`
- Verify: Full test suite

**Interfaces:**
- Consumes: Commands `formulary:backup` dan `formulary:sync-doctors`
- Produces: Penjadwalan otomatis di `routes/console.php` (backup harian 02:00 dan sync dokter dua kali sehari 06:00, 18:00).

- [ ] **Step 1: Write scheduling verification in `tests/Feature/SyncDoctorsCommandTest.php`**

Tambahkan pengujian:
```php
it('registers backup and sync doctors commands in the console schedule', function (): void {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

    $events = collect($schedule->events());
    $commands = $events->map(fn ($event) => $event->command);

    expect($commands->some(fn ($cmd) => str_contains((string) $cmd, 'formulary:backup')))->toBeTrue()
        ->and($commands->some(fn ($cmd) => str_contains((string) $cmd, 'formulary:sync-doctors')))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/SyncDoctorsCommandTest.php`
Expected: FAIL (command tidak ditemukan dalam events schedule).

- [ ] **Step 3: Register schedule in `routes/console.php`**

Perbarui `routes/console.php`:
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('formulary:backup')
    ->dailyAt('02:00')
    ->runInBackground();

Schedule::command('formulary:sync-doctors')
    ->twiceDaily(6, 18)
    ->runInBackground();
```

- [ ] **Step 4: Run complete verification suite**

Run:
```bash
./vendor/bin/pest
./vendor/bin/pint --test
npm run build
```
Expected: All tests pass (>= 46 tests, >= 260 assertions), Pint clean, Vite build successful.

- [ ] **Step 5: Commit**

```bash
git add routes/console.php tests/Feature/SyncDoctorsCommandTest.php
git commit -m "feat(schedule): register automated background backup and doctor sync in console schedule"
```

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-11-17-v1-0-3-sqlite-backup-sync-cli-plan.md`.
Siap dieksekusi secara otonom melalui **Subagent-Driven Development (SDD)**.
