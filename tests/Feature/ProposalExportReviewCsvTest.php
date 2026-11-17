<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('formulary.feed_token', 'test-valid-token');
});

it('exports default 8 columns when include_review is omitted for backward compatibility', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Rudi Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Metformin 500mg',
        'review_status' => 'approved',
        'review_notes' => 'Sesuai Fornas',
        'reviewer_name' => 'apt. Siti',
    ]);

    $response = $this->get('/exports/proposals.csv?token=test-valid-token');
    $response->assertOk();

    $content = $response->streamedContent();
    $lines = explode("\n", trim($content));
    $header = str_getcsv($lines[0]);
    $dataRow = str_getcsv($lines[1]);

    expect(count($header))->toBe(8)
        ->and($header)->toEqual([
            'No',
            'Tanggal',
            'Tahun Periode',
            'Nama Dokter',
            'Nama Obat',
            'Nama Principle',
            'Alasan Klinis',
            'Catatan Pengusul',
        ])
        ->and(count($dataRow))->toBe(8)
        ->and($content)->not->toContain('Sesuai Fornas')
        ->and($content)->not->toContain('apt. Siti');
});

it('exports 12 canonical columns when include_review=1 is requested', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Rudi Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
        'notes' => 'Catatan dokter',
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Metformin 500mg',
        'principal_name' => 'Dexa',
        'clinical_justification' => 'Lini pertama DM',
        'review_status' => 'approved',
        'review_notes' => 'Sesuai Fornas',
        'reviewer_name' => 'apt. Siti',
    ]);

    $response = $this->get('/exports/proposals.csv?token=test-valid-token&include_review=1');
    $response->assertOk();

    $content = $response->streamedContent();
    $lines = explode("\n", trim($content));
    $header = str_getcsv($lines[0]);
    $dataRow = str_getcsv($lines[1]);

    expect(count($header))->toBe(12)
        ->and($header)->toEqual([
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
        ])
        ->and(count($dataRow))->toBe(12)
        ->and($dataRow[0])->toBe('1')
        ->and($dataRow[1])->toBe('17/11/2026')
        ->and($dataRow[2])->toBe('2026')
        ->and($dataRow[3])->toBe('dr. Rudi Sp.PD')
        ->and($dataRow[4])->toBe('Metformin 500mg')
        ->and($dataRow[5])->toBe('Dexa')
        ->and($dataRow[6])->toBe('Lini pertama DM')
        ->and($dataRow[7])->toBe('Catatan dokter')
        ->and($dataRow[8])->not->toBeEmpty()
        ->and($dataRow[9])->toBe('approved')
        ->and($dataRow[10])->toBe('Sesuai Fornas')
        ->and($dataRow[11])->toBe('apt. Siti');
});

it('exports Excel XLSX with 12 canonical columns and styled headers', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Zaidan Sp.A',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
        'notes' => 'Catatan dokter pengusul',
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Cefixime Sirup',
        'principal_name' => 'Kimia Farma',
        'clinical_justification' => 'Infeksi bakteri saluran cerna',
        'review_status' => 'approved',
        'review_notes' => 'Disetujui rapat KFT',
        'reviewer_name' => 'apt. Hendra',
    ]);

    $response = $this->get(route('exports.proposals.xlsx', ['period_year' => 2026]))->assertOk();

    expect($response->headers->get('Content-Type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmpFile = tempnam(sys_get_temp_dir(), 'test_review_xlsx_').'.xlsx';
    file_put_contents($tmpFile, $response->streamedContent());

    $reader = new Reader;
    $reader->open($tmpFile);

    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($tmpFile);

    // OpenSpout reader skips empty spacer rows:
    // Row 0: Title, Row 1: Subtitle/Meta, Row 2: Header Row, Row 3: Data Row
    expect($rows)->toHaveCount(4);

    $headerRow = $rows[2];
    expect(count($headerRow))->toBe(12)
        ->and($headerRow)->toEqual([
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

    $dataRow = $rows[3];
    expect(count($dataRow))->toBe(12)
        ->and($dataRow[0])->toBe(1)
        ->and($dataRow[1])->toBe('17/11/2026')
        ->and($dataRow[2])->toBe(2026)
        ->and($dataRow[3])->toBe('dr. Zaidan Sp.A')
        ->and($dataRow[4])->toBe('Cefixime Sirup')
        ->and($dataRow[5])->toBe('Kimia Farma')
        ->and($dataRow[6])->toBe('Infeksi bakteri saluran cerna')
        ->and($dataRow[7])->toBe('Catatan dokter pengusul')
        ->and($dataRow[8])->not->toBeEmpty()
        ->and($dataRow[9])->toBe('approved')
        ->and($dataRow[10])->toBe('Disetujui rapat KFT')
        ->and($dataRow[11])->toBe('apt. Hendra');
});

it('sanitizes formula injection in review notes and penelaah when exporting', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Rudi Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Metformin 500mg',
        'review_status' => 'rejected',
        'review_notes' => '=SUM(1,2)',
        'reviewer_name' => '@Apoteker',
    ]);

    // CSV test with include_review=1
    $response = $this->get('/exports/proposals.csv?token=test-valid-token&include_review=1');
    $content = $response->streamedContent();
    $lines = explode("\n", trim($content));
    $dataRow = str_getcsv($lines[1]);

    expect($dataRow[10])->toBe("'=SUM(1,2)")
        ->and($dataRow[11])->toBe("'@Apoteker");
});

it('handles null review notes and null reviewer as dash in opt-in csv and xlsx', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Rudi Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Paracetamol Drop',
        'review_status' => 'pending',
        'review_notes' => null,
        'reviewer_name' => null,
    ]);

    // CSV test
    $response = $this->get('/exports/proposals.csv?token=test-valid-token&include_review=1');
    $content = $response->streamedContent();
    $lines = explode("\n", trim($content));
    $dataRow = str_getcsv($lines[1]);

    expect($dataRow[9])->toBe('pending')
        ->and($dataRow[10])->toBe('-')
        ->and($dataRow[11])->toBe('-');

    // XLSX test
    $responseXlsx = $this->get(route('exports.proposals.xlsx'))->assertOk();
    $tmpFile = tempnam(sys_get_temp_dir(), 'test_review_null_xlsx_').'.xlsx';
    file_put_contents($tmpFile, $responseXlsx->streamedContent());

    $reader = new Reader;
    $reader->open($tmpFile);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    @unlink($tmpFile);

    $dataRowXlsx = $rows[3];
    expect($dataRowXlsx[9])->toBe('pending')
        ->and($dataRowXlsx[10])->toBe('-')
        ->and($dataRowXlsx[11])->toBe('-');
});
