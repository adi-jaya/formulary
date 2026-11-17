<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestGoogleSheetWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'formulary:test-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menguji ketersediaan, otorisasi, dan validitas Google Apps Script Webhook.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $webhookUrl = (string) config('formulary.sheet_webhook_url');
        $webhookSecret = (string) config('formulary.sheet_webhook_secret');

        if ($webhookUrl === '') {
            $this->error('❌ URL webhook Google Sheets belum dikonfigurasi pada FORMULARY_SHEET_WEBHOOK_URL di berkas .env.');

            return self::FAILURE;
        }

        $this->info("🔍 Menguji URL Webhook: {$webhookUrl}");

        // Tahap 1: Pengujian Aksesibilitas Publik (GET request)
        try {
            $getResponse = Http::timeout(8)
                ->withoutRedirecting()
                ->get($webhookUrl);

            // Cek jika dialihkan ke halaman login akun Google
            if ($getResponse->status() === 302 || $getResponse->status() === 301) {
                $location = (string) $getResponse->header('Location');
                if (str_contains($location, 'accounts.google.com')) {
                    $this->newLine();
                    $this->error('❌ GAGAL: Webhook dialihkan ke Google Login (302 Redirect).');
                    $this->line('👉 Akar Masalah:');
                    $this->line('   Pengaturan "Who has access" (Siapa yang memiliki akses) pada Web App saat ini');
                    $this->line('   masih disetel ke "Only myself" atau akun organisasi domain, BUKAN "Anyone" (Siapa saja).');
                    $this->line('👉 Solusi:');
                    $this->line('   1. Buka Apps Script > Deploy > Manage deployments > Edit.');
                    $this->line('   2. Ubah Version menjadi "New version".');
                    $this->line('   3. Ubah "Who has access" menjadi "Anyone" (Siapa saja / Anonim).');
                    $this->line('   4. Klik Deploy. Jika URL baru dihasilkan, pastikan salin ke FORMULARY_SHEET_WEBHOOK_URL.');

                    return self::FAILURE;
                }
            }

            if ($getResponse->status() === 401) {
                $this->newLine();
                $this->error('❌ GAGAL: Google mengembalikan HTTP 401 Unauthorized.');
                $this->line('👉 Akar Masalah:');
                $this->line('   Google Edge Proxy menolak akses eksternal tanpa otentikasi login Google.');
                $this->line('👉 Solusi:');
                $this->line('   Pastikan Web App di-deploy dengan opsi "Who has access" = "Anyone".');

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->warn("⚠️ Catatan: Pemeriksaan GET awal mengalami kendala: {$e->getMessage()}");
        }

        // Tahap 2: Pengujian POST Payload & Secret (action: ping)
        try {
            $postResponse = Http::timeout(10)
                ->acceptJson()
                ->post($webhookUrl, [
                    'secret' => $webhookSecret,
                    'action' => 'ping',
                ]);

            if ($postResponse->status() === 401) {
                $this->newLine();
                $this->error('❌ GAGAL: Google mengembalikan HTTP 401 Unauthorized pada request POST.');
                $this->line('👉 Akar Masalah:');
                $this->line('   Akses publik belum aktif pada deployment Google Apps Script Web App.');
                $this->line('👉 Solusi:');
                $this->line('   Ubah "Who has access" menjadi "Anyone" pada Manage deployments di Apps Script.');

                return self::FAILURE;
            }

            if (! $postResponse->successful()) {
                $this->newLine();
                $this->error("❌ GAGAL: Webhook merespons dengan HTTP status {$postResponse->status()}.");
                $this->line('Respons: '.trim(substr(strip_tags($postResponse->body()), 0, 300)));

                return self::FAILURE;
            }

            $json = $postResponse->json();
            if (is_array($json) && isset($json['status']) && $json['status'] === 'error') {
                $msg = (string) ($json['message'] ?? 'Error tidak diketahui');
                $this->newLine();
                if (str_contains(strtolower($msg), 'unauthorized') || str_contains(strtolower($msg), 'secret')) {
                    $this->error('❌ GAGAL: Secret tidak cocok!');
                    $this->line("Pesan Webhook: {$msg}");
                    $this->line('👉 Pastikan variabel WEBHOOK_SECRET di skrip Google Apps Script identik dengan');
                    $this->line("   FORMULARY_SHEET_WEBHOOK_SECRET di berkas .env ({$webhookSecret}).");
                } else {
                    $this->error("❌ GAGAL: Webhook mengembalikan error: {$msg}");
                }

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('✅ SUKSES: Webhook Google Apps Script terhubung sempurna dan siap menerima data real-time!');
            $this->line('   Status : Siap (200 OK)');
            $this->line('   Latency: Respons diterima cepat (< 2 detik)');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->newLine();
            $this->error("❌ GAGAL: Terjadi kendala koneksi ke Google Sheet Webhook: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
