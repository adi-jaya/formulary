<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DoctorDirectoryService;
use Illuminate\Console\Command;
use Throwable;

class SyncDoctorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formulary:sync-doctors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menyinkronkan daftar dokter aktif dari API Portal Sifast ke cache dan snapshot lokal';

    /**
     * Execute the console command.
     */
    public function handle(DoctorDirectoryService $doctorService): int
    {
        $this->info('Menyinkronkan data dokter dari API Portal Sifast...');

        try {
            $doctors = $doctorService->refreshFromApi();
            $count = count($doctors);

            $this->info("Sinkronisasi data dokter berhasil: {$count} dokter aktif disinkronkan ke cache dan snapshot lokal.");

            $preview = array_slice($doctors, 0, 5);
            $rows = array_map(
                fn (array $doc, int $idx): array => [
                    (string) ($idx + 1),
                    $doc['name'],
                    $doc['clinic'] ?? '-',
                ],
                $preview,
                array_keys($preview)
            );

            $this->table(['No', 'Nama Dokter', 'Poli / Klinik'], $rows);

            if ($count > 5) {
                $remaining = $count - 5;
                $this->line("... dan {$remaining} dokter lainnya.");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Gagal menghubungi API Portal Sifast: {$e->getMessage()}. Snapshot lokal tetap dipertahankan.");

            return self::FAILURE;
        }
    }
}
