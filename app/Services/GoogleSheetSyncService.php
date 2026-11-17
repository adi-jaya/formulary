<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Proposal;
use App\Queries\ProposalItemRecapQuery;
use App\Support\SpreadsheetSanitizer;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleSheetSyncService
{
    /**
     * Mendorong data usulan baru secara instan ke Google Sheets Webhook (< 2 detik).
     */
    public function pushNewProposal(Proposal $proposal): bool
    {
        $webhookUrl = (string) config('formulary.sheet_webhook_url');
        if ($webhookUrl === '') {
            return false;
        }

        $proposal->loadMissing('items');
        if ($proposal->items->isEmpty()) {
            return false;
        }

        $proposalDateStr = $proposal->proposal_date instanceof DateTimeInterface
            ? $proposal->proposal_date->format('d/m/Y')
            : Carbon::parse((string) $proposal->proposal_date)->format('d/m/Y');

        $rows = [];
        foreach ($proposal->items as $index => $item) {
            $createdAtStr = '-';
            if ($item->created_at) {
                $createdAtStr = $item->created_at instanceof DateTimeInterface
                    ? $item->created_at->format('d/m/Y H:i:s')
                    : Carbon::parse((string) $item->created_at)->format('d/m/Y H:i:s');
            }

            $rows[] = [
                'no' => $index + 1,
                'proposal_date' => $proposalDateStr,
                'period_year' => (int) $proposal->period_year,
                'doctor_name' => SpreadsheetSanitizer::sanitizeCell($proposal->doctor_name),
                'medicine_name' => SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                'principal_name' => $item->principal_name ? SpreadsheetSanitizer::sanitizeCell($item->principal_name) : '-',
                'clinical_justification' => $item->clinical_justification ? SpreadsheetSanitizer::sanitizeCell($item->clinical_justification) : '-',
                'notes' => $proposal->notes ? SpreadsheetSanitizer::sanitizeCell($proposal->notes) : '-',
                'created_at' => $createdAtStr,
                'review_status' => $item->review_status ?? 'pending',
                'review_notes' => $item->review_notes ? SpreadsheetSanitizer::sanitizeCell($item->review_notes) : '-',
                'reviewer_name' => ($item->reviewer_name !== null && $item->reviewer_name !== '')
                    ? SpreadsheetSanitizer::sanitizeCell($item->reviewer_name)
                    : '-',
            ];
        }

        try {
            $response = Http::timeout(5)
                ->retry(2, 100, throw: false)
                ->acceptJson()
                ->post($webhookUrl, [
                    'secret' => (string) config('formulary.sheet_webhook_secret'),
                    'action' => 'append_rows',
                    'rows' => $rows,
                ]);

            if (! $response->successful() || $response->json('status') !== 'success') {
                $errDetail = $response->json('message') ?? trim(substr(strip_tags($response->body()), 0, 200));
                Log::warning("Sinkronisasi Google Sheet gagal (HTTP {$response->status()}): {$errDetail}");

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Pengecualian saat menghubungi Google Sheet Webhook: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Sinkronisasi massal seluruh data usulan ke Google Sheets.
     *
     * @return int Jumlah baris yang berhasil dikirim
     */
    public function syncAll(?int $periodYear = null, bool $clearFirst = false): int
    {
        $webhookUrl = (string) config('formulary.sheet_webhook_url');
        if ($webhookUrl === '') {
            return 0;
        }

        if ($clearFirst) {
            try {
                $clearResponse = Http::timeout(10)
                    ->retry(2, 100, throw: false)
                    ->acceptJson()
                    ->post($webhookUrl, [
                        'secret' => (string) config('formulary.sheet_webhook_secret'),
                        'action' => 'clear_sheet',
                    ]);

                if (! $clearResponse->successful() || $clearResponse->json('status') !== 'success') {
                    $errDetail = $clearResponse->json('message') ?? trim(substr(strip_tags($clearResponse->body()), 0, 200));
                    Log::warning("Pembersihan Google Sheet gagal (HTTP {$clearResponse->status()}): {$errDetail}");
                }
            } catch (Throwable $e) {
                Log::warning('Pengecualian saat membersihkan Google Sheet: '.$e->getMessage());
            }
        }

        $filters = array_filter(['period_year' => $periodYear]);
        $query = ProposalItemRecapQuery::build($filters);
        $totalSent = 0;
        $counter = 1;

        $query->chunk(200, function ($items) use ($webhookUrl, &$totalSent, &$counter): void {
            $rows = [];
            foreach ($items as $item) {
                $createdAtStr = '-';
                if ($item->created_at) {
                    $createdAtStr = $item->created_at instanceof DateTimeInterface
                        ? $item->created_at->format('d/m/Y H:i:s')
                        : Carbon::parse((string) $item->created_at)->format('d/m/Y H:i:s');
                }

                $proposalDateStr = '-';
                if ($item->proposal_date) {
                    $proposalDateStr = $item->proposal_date instanceof DateTimeInterface
                        ? $item->proposal_date->format('d/m/Y')
                        : Carbon::parse((string) $item->proposal_date)->format('d/m/Y');
                }

                $rows[] = [
                    'no' => $counter++,
                    'proposal_date' => $proposalDateStr,
                    'period_year' => (int) $item->period_year,
                    'doctor_name' => SpreadsheetSanitizer::sanitizeCell($item->doctor_name),
                    'medicine_name' => SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                    'principal_name' => $item->principal_name ? SpreadsheetSanitizer::sanitizeCell($item->principal_name) : '-',
                    'clinical_justification' => $item->clinical_justification ? SpreadsheetSanitizer::sanitizeCell($item->clinical_justification) : '-',
                    'notes' => $item->notes ? SpreadsheetSanitizer::sanitizeCell($item->notes) : '-',
                    'created_at' => $createdAtStr,
                    'review_status' => $item->review_status ?? 'pending',
                    'review_notes' => $item->review_notes ? SpreadsheetSanitizer::sanitizeCell($item->review_notes) : '-',
                    'reviewer_name' => ($item->reviewer_name !== null && $item->reviewer_name !== '')
                    ? SpreadsheetSanitizer::sanitizeCell($item->reviewer_name)
                    : '-',
                ];
            }

            try {
                $response = Http::timeout(15)
                    ->retry(2, 100, throw: false)
                    ->acceptJson()
                    ->post($webhookUrl, [
                        'secret' => (string) config('formulary.sheet_webhook_secret'),
                        'action' => 'append_rows',
                        'rows' => $rows,
                    ]);

                if (! $response->successful() || $response->json('status') !== 'success') {
                    $errDetail = $response->json('message') ?? trim(substr(strip_tags($response->body()), 0, 200));
                    Log::warning("Sinkronisasi massal Google Sheet gagal (HTTP {$response->status()}): {$errDetail}");
                } else {
                    $totalSent += count($rows);
                }
            } catch (Throwable $e) {
                Log::warning('Pengecualian saat sinkronisasi massal Google Sheet: '.$e->getMessage());
            }
        });

        return $totalSent;
    }
}
