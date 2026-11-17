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

it('uses default retention from config when retention option is omitted', function (): void {
    $backupDir = storage_path('framework/testing/backups_default');
    config([
        'formulary.backup_path' => $backupDir,
        'formulary.backup_retention_days' => 10,
    ]);
    File::ensureDirectoryExists($backupDir);
    File::cleanDirectory($backupDir);

    $oldFile = $backupDir.'/formulary_2020-01-01_000000.sqlite';
    file_put_contents($oldFile, 'dummy stale sqlite');
    touch($oldFile, now()->subDays(15)->getTimestamp());

    $recentFile = $backupDir.'/formulary_2026-01-01_000000.sqlite';
    file_put_contents($recentFile, 'dummy recent sqlite');
    touch($recentFile, now()->subDays(5)->getTimestamp());

    $this->artisan('formulary:backup')
        ->expectsOutputToContain('Backup basis data berhasil')
        ->assertSuccessful();

    expect(file_exists($oldFile))->toBeFalse()
        ->and(file_exists($recentFile))->toBeTrue();

    File::deleteDirectory($backupDir);
});
