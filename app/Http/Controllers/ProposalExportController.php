<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\ProposalItemRecapQuery;
use App\Services\ProposalExportService;
use App\Support\SpreadsheetSanitizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalExportController extends Controller
{
    /**
     * Stream token-protected sanitized live CSV feed.
     */
    public function csv(Request $request): StreamedResponse
    {
        $configuredToken = config('formulary.feed_token');
        $requestToken = (string) $request->query('token', '');

        if (! is_string($configuredToken) || $configuredToken === '' || ! hash_equals($configuredToken, $requestToken)) {
            abort(403, 'Akses live CSV feed ditolak: token tidak valid.');
        }

        $includeReview = $request->boolean('include_review');

        $filters = [
            'period_year' => $request->query('period_year'),
            'doctor' => $request->query('doctor'),
            'principal' => $request->query('principal'),
            'review_status' => $request->query('review_status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
        ];

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="proposals_'.now()->format('Ymd_His').'.csv"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ];

        return response()->stream(function () use ($filters, $includeReview): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            if ($includeReview) {
                fputcsv($handle, [
                    'No',
                    'Tanggal',
                    'Tahun Periode',
                    'Nama Dokter',
                    'Nama Obat',
                    'Nama Principle',
                    'Alasan Klinis',
                    'Catatan Pengusul',
                    'Waktu Input',
                    'Status Telaah',
                    'Catatan Telaah',
                    'Penelaah',
                ]);
            } else {
                fputcsv($handle, [
                    'No',
                    'Tanggal',
                    'Tahun Periode',
                    'Nama Dokter',
                    'Nama Obat',
                    'Nama Principle',
                    'Alasan Klinis',
                    'Catatan Pengusul',
                ]);
            }

            $query = ProposalItemRecapQuery::build($filters);
            $no = 1;

            $query->chunk(250, function ($items) use ($handle, $includeReview, &$no): void {
                foreach ($items as $item) {
                    $principal = $item->principal_name !== null && $item->principal_name !== ''
                        ? SpreadsheetSanitizer::sanitizeCell($item->principal_name)
                        : '-';
                    $justification = $item->clinical_justification !== null && $item->clinical_justification !== ''
                        ? SpreadsheetSanitizer::sanitizeCell($item->clinical_justification)
                        : '-';
                    $notes = $item->notes !== null && $item->notes !== ''
                        ? SpreadsheetSanitizer::sanitizeCell($item->notes)
                        : '-';

                    if ($includeReview) {
                        $createdAt = $item->created_at !== null
                            ? Carbon::parse($item->created_at)->format('d/m/Y H:i:s')
                            : '-';
                        $reviewStatus = $item->review_status !== null && $item->review_status !== ''
                            ? SpreadsheetSanitizer::sanitizeCell($item->review_status)
                            : 'pending';
                        $reviewNotes = $item->review_notes !== null && $item->review_notes !== ''
                            ? SpreadsheetSanitizer::sanitizeCell($item->review_notes)
                            : '-';
                        $reviewerName = $item->reviewer_name !== null && $item->reviewer_name !== ''
                            ? SpreadsheetSanitizer::sanitizeCell($item->reviewer_name)
                            : '-';

                        fputcsv($handle, [
                            $no++,
                            Carbon::parse($item->proposal_date)->format('d/m/Y'),
                            $item->period_year,
                            SpreadsheetSanitizer::sanitizeCell($item->doctor_name),
                            SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                            $principal,
                            $justification,
                            $notes,
                            $createdAt,
                            $reviewStatus,
                            $reviewNotes,
                            $reviewerName,
                        ]);
                    } else {
                        fputcsv($handle, [
                            $no++,
                            Carbon::parse($item->proposal_date)->format('d/m/Y'),
                            $item->period_year,
                            SpreadsheetSanitizer::sanitizeCell($item->doctor_name),
                            SpreadsheetSanitizer::sanitizeCell($item->medicine_name),
                            $principal,
                            $justification,
                            $notes,
                        ]);
                    }
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Download styled Excel XLSX report.
     */
    public function xlsx(Request $request, ProposalExportService $exportService): StreamedResponse
    {
        $rawSortBy = $request->query('sort_by');
        $sortBy = $rawSortBy !== null && $rawSortBy !== '' ? (string) $rawSortBy : 'date';
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $filters = [
            'period_year' => $request->query('period_year'),
            'doctor' => $request->query('doctor'),
            'principal' => $request->query('principal'),
            'review_status' => $request->query('review_status'),
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
            'search' => $request->query('search'),
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];

        $tempPath = $exportService->generateXlsx($filters);
        $filename = 'rekap_formularium_rs_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($tempPath): void {
            $stream = fopen($tempPath, 'rb');
            if ($stream !== false) {
                fpassthru($stream);
                fclose($stream);
            }
            @unlink($tempPath);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
