<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the consensus tab with metrics and switches between items and consensus tabs', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. Budi Sp.An', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Justifikasi A']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Siti Sp.PD', 'proposal_date' => '2026-11-02', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Justifikasi B']);

    // Default tab is items
    $this->get(route('proposals.index'))
        ->assertOk()
        ->assertSee('Daftar Usulan Item (Tabel)')
        ->assertSee('Analitik Konsensus Obat')
        ->assertSee('tab=consensus', false);

    // Consensus tab renders consensus view data
    $response = $this->get(route('proposals.index', ['tab' => 'consensus']))->assertOk();
    $response->assertSee('Analitik Konsensus Usulan Obat')
        ->assertSee('Diarvit 100 Mg Tab')
        ->assertSee('2 Dokter')
        ->assertSee('dr. Budi Sp.An')
        ->assertSee('dr. Siti Sp.PD')
        ->assertSee('Justifikasi A')
        ->assertSee('Justifikasi B');
});

it('preserves filters when switching tabs and drops page parameter from tab links', function (): void {
    $response = $this->get(route('proposals.index', [
        'period_year' => 2026,
        'search' => 'Diarvit',
        'page' => 2,
    ]))->assertOk();

    // The consensus tab URL preserves period_year and search, but drops page
    $consensusTabUrl = route('proposals.index', [
        'period_year' => 2026,
        'search' => 'Diarvit',
        'tab' => 'consensus',
    ]);

    $itemsTabUrl = route('proposals.index', [
        'period_year' => 2026,
        'search' => 'Diarvit',
        'tab' => 'items',
    ]);

    $response->assertSee(e($consensusTabUrl), false)
        ->assertSee(e($itemsTabUrl), false);
});

it('supports quick filtering for consensus medicines and renders KPI summary cards', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. Budi', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Obat Multi DPJP', 'principal_name' => 'Kalbe', 'clinical_justification' => 'Pilihan utama terapi']);
    $p1->items()->create(['medicine_name' => 'Obat Single DPJP', 'principal_name' => 'Sanbe']);

    $p2 = Proposal::create(['doctor_name' => 'dr. Ahmad', 'proposal_date' => '2026-11-02', 'period_year' => 2026, 'notes' => 'Catatan dokter Ahmad']);
    $p2->items()->create(['medicine_name' => 'Obat Multi DPJP', 'principal_name' => 'Dexa']);

    $response = $this->get(route('proposals.index', ['tab' => 'consensus', 'min_doctors' => 2]))->assertOk();
    $response->assertSee('Obat Multi DPJP')
        ->assertDontSee('Obat Single DPJP')
        ->assertSee('Hanya Usulan Konsensus (≥ 2 Dokter)')
        ->assertSee('Semua Obat')
        ->assertSee('Total Obat Unik Diusulkan')
        ->assertSee('Obat Konsensus Multidokter')
        ->assertSee('Dukungan Tertinggi')
        ->assertSee('Kalbe')
        ->assertSee('Dexa')
        ->assertSee('Pilihan utama terapi')
        ->assertSee('Catatan dokter Ahmad');
});

it('supports search filtering and displays empty state when no consensus items match', function (): void {
    $p = Proposal::create(['doctor_name' => 'dr. Budi', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p->items()->create(['medicine_name' => 'Paracetamol 500 Mg']);

    // Match
    $this->get(route('proposals.index', ['tab' => 'consensus', 'search' => 'Paracetamol']))
        ->assertOk()
        ->assertSee('Paracetamol 500 Mg')
        ->assertSee('1 Dokter');

    // No match
    $this->get(route('proposals.index', ['tab' => 'consensus', 'search' => 'Amoxicillin']))
        ->assertOk()
        ->assertDontSee('Paracetamol 500 Mg')
        ->assertSee('Tidak ada usulan obat konsensus.');
});
