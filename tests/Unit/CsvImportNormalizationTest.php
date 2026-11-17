<?php

declare(strict_types=1);

use App\Models\ReferenceMedicine;
use App\Services\FormularyCsvImporter;
use App\Support\SpreadsheetSanitizer;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('normalizes whitespace, dash-only principals, and known csv typo aliases', function (): void {
    expect(TextNormalizer::collapseWhitespace("  Diarvit   100 Mg \t Tab "))->toBe('Diarvit 100 Mg Tab')
        ->and(TextNormalizer::normalizePrincipal(' - '))->toBeNull()
        ->and(TextNormalizer::normalizePrincipal('   '))->toBeNull()
        ->and(TextNormalizer::normalizePrincipal('Novel'))->toBe('Novell')
        ->and(TextNormalizer::normalizePrincipal('generik novell'))->toBe('Novell Generik');
});

it('strips business entity prefixes like pt and cv and resolves bernofarm alias', function (): void {
    expect(TextNormalizer::normalizePrincipal('PT. Novell Pharma'))->toBe('Novell')
        ->and(TextNormalizer::normalizePrincipal('CV Sanbe Farma'))->toBe('Sanbe')
        ->and(TextNormalizer::normalizePrincipal('PT. Bernofarm'))->toBe('Berno')
        ->and(TextNormalizer::normalizePrincipal('UD. Kimia Farma'))->toBe('Kimia Farma')
        ->and(TextNormalizer::normalizePrincipal('PT Kalbe Farma'))->toBe('Kalbe Farma');
});

it('escapes spreadsheet formula injection prefixes without corrupting empty or normal values', function (): void {
    expect(SpreadsheetSanitizer::sanitizeCell('=HYPERLINK("http://evil")'))->toBe("'=HYPERLINK(\"http://evil\")")
        ->and(SpreadsheetSanitizer::sanitizeCell('+628123'))->toBe("'+628123")
        ->and(SpreadsheetSanitizer::sanitizeCell('-100'))->toBe("'-100")
        ->and(SpreadsheetSanitizer::sanitizeCell('@sum(1,2)'))->toBe("'@sum(1,2)")
        ->and(SpreadsheetSanitizer::sanitizeCell(null))->toBe('')
        ->and(SpreadsheetSanitizer::sanitizeCell('Diarvit 100 Mg Tab'))->toBe('Diarvit 100 Mg Tab');
});

it('escapes spreadsheet formula injection prefixes with leading whitespace and dangerous owasp characters', function (): void {
    expect(SpreadsheetSanitizer::sanitizeCell('   =SUM(A1:A10)'))->toBe("'   =SUM(A1:A10)")
        ->and(SpreadsheetSanitizer::sanitizeCell('  +cmd|'))->toBe("'  +cmd|")
        ->and(SpreadsheetSanitizer::sanitizeCell('|powershell'))->toBe("'|powershell")
        ->and(SpreadsheetSanitizer::sanitizeCell('%COMSPEC%'))->toBe("'%COMSPEC%")
        ->and(SpreadsheetSanitizer::sanitizeCell("\t=1+1"))->toBe("'\t=1+1")
        ->and(SpreadsheetSanitizer::sanitizeCell('Normal Text'))->toBe('Normal Text');
});

it('imports the hospital formulary csv idempotently including multiline quoted rows and comma decimals', function (): void {
    $path = database_path('seeders/data/formulary_medicines.csv');

    $firstRun = app(FormularyCsvImporter::class)->importFromPath($path);
    $secondRun = app(FormularyCsvImporter::class)->importFromPath($path);

    expect($firstRun['imported'])->toBeGreaterThanOrEqual(205)
        ->and(ReferenceMedicine::count())->toBe($firstRun['imported'])
        ->and(ReferenceMedicine::count())->toBe($secondRun['imported'])
        ->and(ReferenceMedicine::where('medicine_name', 'Nuzolam 0,25 Mg')->value('principal_name'))->toBe('Nulab')
        ->and(ReferenceMedicine::where('medicine_name', 'Puspacal')->value('principal_name'))->toBe('Dipa')
        ->and(ReferenceMedicine::where('medicine_name', 'L-Vit D3 Drop')->value('principal_name'))->toBe('Lapi')
        ->and(ReferenceMedicine::where('medicine_name', 'Pyridostigmin 60 Mg Tab')->value('principal_name'))->toBe('Novell')
        ->and(ReferenceMedicine::where('medicine_name', 'Paracetamol Drop')->value('principal_name'))->toBeNull();
});
