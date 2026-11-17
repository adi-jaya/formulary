<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formulary:backup {--retention= : Jumlah hari retensi penyimpanan berkas backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Membuat salinan cadangan (backup) basis data SQLite menggunakan VACUUM INTO dan rotasi retensi';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->error('Perintah backup formulary:backup hanya mendukung driver SQLite.');

            return self::FAILURE;
        }

        $backupPath = (string) config('formulary.backup_path', storage_path('app/backups'));
        File::ensureDirectoryExists($backupPath);

        $fileName = 'formulary_'.now()->format('Y-m-d_His').'.sqlite';
        $targetPath = rtrim($backupPath, '/\\').DIRECTORY_SEPARATOR.$fileName;

        if (File::exists($targetPath)) {
            File::delete($targetPath);
        }

        $startTime = microtime(true);
        DB::statement('VACUUM INTO ?', [$targetPath]);
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $sizeBytes = File::exists($targetPath) ? File::size($targetPath) : 0;
        $sizeFormatted = $this->formatBytes($sizeBytes);

        $retentionOption = $this->option('retention');
        $retentionDays = is_numeric($retentionOption) && (int) $retentionOption > 0
            ? (int) $retentionOption
            : max(1, (int) config('formulary.backup_retention_days', 30));

        $thresholdTimestamp = now()->subDays($retentionDays)->getTimestamp();
        $backupFiles = File::glob(rtrim($backupPath, '/\\').DIRECTORY_SEPARATOR.'formulary_*.sqlite') ?: [];

        $prunedCount = 0;
        foreach ($backupFiles as $file) {
            if ($file === $targetPath) {
                continue;
            }

            if (File::lastModified($file) < $thresholdTimestamp) {
                File::delete($file);
                $prunedCount++;
            }
        }

        $this->info("Backup basis data berhasil dibuat: {$fileName} ({$sizeFormatted}, {$durationMs} ms)");

        if ($prunedCount > 0) {
            $this->line("Dibersihkan {$prunedCount} berkas backup lama (> {$retentionDays} hari).");
        }

        return self::SUCCESS;
    }

    /**
     * Format ukuran bita ke satuan yang mudah dibaca.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
