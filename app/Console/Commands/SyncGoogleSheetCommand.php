<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Queries\ProposalItemRecapQuery;
use App\Services\GoogleSheetSyncService;
use Illuminate\Console\Command;

class SyncGoogleSheetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formulary:sync-sheets {--year= : Filter tahun periode} {--clear : Bersihkan baris ke-2 ke bawah sebelum menulis ulang}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengirimkan data usulan obat secara massal ke Google Sheets Webhook.';

    /**
     * Execute the console command.
     */
    public function handle(GoogleSheetSyncService $syncService): int
    {
        $webhookUrl = (string) config('formulary.sheet_webhook_url');
        if ($webhookUrl === '') {
            $this->warn('Peringatan: URL webhook Google Sheets belum dikonfigurasi pada FORMULARY_SHEET_WEBHOOK_URL.');

            return self::FAILURE;
        }

        $clearFirst = (bool) $this->option('clear');

        if (! $clearFirst && ! $this->confirm('Perhatian: Sinkronisasi tanpa --clear akan menambahkan seluruh baris ke bawah lembar kerja. Lanjutkan?', false)) {
            $this->info('Sinkronisasi dibatalkan.');

            return self::SUCCESS;
        }

        $yearOption = $this->option('year');
        $year = $yearOption !== null ? (int) $yearOption : null;

        $this->info('Memulai sinkronisasi data usulan ke Google Sheets...');

        $count = $syncService->syncAll($year, $clearFirst);

        $totalAvailable = ProposalItemRecapQuery::build(array_filter(['period_year' => $year]))->count();

        if ($totalAvailable > 0 && $count === 0) {
            $this->warn('Peringatan: Tidak ada baris usulan yang berhasil dikirim ke Google Sheets. Periksa status webhook dan storage/logs/laravel.log.');

            return self::FAILURE;
        }

        $this->info("Sinkronisasi massal ke Google Sheets berhasil: {$count} baris usulan terkirim.");

        return self::SUCCESS;
    }
}
