<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ReferenceMedicine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('previews principal normalizations without mutating database in dry-run mode', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create(['medicine_name' => 'Obat A', 'principal_name' => 'PT. Novell']);
    $ref = ReferenceMedicine::create(['medicine_name' => 'Obat B', 'principal_name' => 'PT. Bernofarm']);

    $this->artisan('formulary:normalize-principals', ['--dry-run' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertSuccessful();

    expect($item->fresh()->principal_name)->toBe('PT. Novell')
        ->and($ref->fresh()->principal_name)->toBe('PT. Bernofarm');
});

it('applies principal normalizations in database when run without dry-run', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create(['medicine_name' => 'Obat A', 'principal_name' => 'PT. Novell']);
    $ref = ReferenceMedicine::create(['medicine_name' => 'Obat B', 'principal_name' => 'PT. Bernofarm']);

    $this->artisan('formulary:normalize-principals')
        ->assertSuccessful();

    expect($item->fresh()->principal_name)->toBe('Novell')
        ->and($ref->fresh()->principal_name)->toBe('Berno');
});

it('handles already normalized records gracefully', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create(['medicine_name' => 'Obat A', 'principal_name' => 'Novell']);
    $ref = ReferenceMedicine::create(['medicine_name' => 'Obat B', 'principal_name' => 'Berno']);

    $this->artisan('formulary:normalize-principals')
        ->assertSuccessful();

    expect($item->fresh()->principal_name)->toBe('Novell')
        ->and($ref->fresh()->principal_name)->toBe('Berno');
});

it('normalizes dash characters to null in database', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create(['medicine_name' => 'Obat A', 'principal_name' => '---']);

    $this->artisan('formulary:normalize-principals')
        ->assertSuccessful();

    expect($item->fresh()->principal_name)->toBeNull();
});
