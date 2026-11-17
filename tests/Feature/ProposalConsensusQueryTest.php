<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Queries\ProposalConsensusQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('aggregates medicines by distinct doctors and ranks multi-doctor consensus on top', function (): void {
    // Proposal 1: dr. Budi proposes Paracetamol and Diarvit
    $p1 = Proposal::create(['doctor_name' => 'dr. Budi Sp.An', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Paracetamol 500 Mg', 'principal_name' => 'Sanbe', 'clinical_justification' => 'Analgetik aman']);
    $p1->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Diare akut']);

    // Proposal 2: dr. Siti also proposes Diarvit
    $p2 = Proposal::create(['doctor_name' => 'dr. Siti Sp.PD', 'proposal_date' => '2026-11-02', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'First-line diare']);

    // Proposal 3: dr. Siti proposes Diarvit again (same doctor duplicate proposal)
    $p3 = Proposal::create(['doctor_name' => 'dr. Siti Sp.PD', 'proposal_date' => '2026-11-03', 'period_year' => 2026]);
    $p3->items()->create(['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell', 'clinical_justification' => 'Tambahan bangsal']);

    $paginator = ProposalConsensusQuery::paginate(['period_year' => 2026]);
    $items = $paginator->items();

    expect($items)->toHaveCount(2);

    // Diarvit has 2 distinct doctors, 3 total proposals -> Ranked #1
    $top = $items[0];
    expect($top->medicine_name)->toBe('Diarvit 100 Mg Tab')
        ->and((int) $top->distinct_doctors_count)->toBe(2)
        ->and((int) $top->total_proposals_count)->toBe(3)
        ->and($top->proposals)->toHaveCount(3)
        ->and($top->principals)->toBe(['Novell']);

    // Paracetamol has 1 doctor, 1 proposal -> Ranked #2
    $second = $items[1];
    expect($second->medicine_name)->toBe('Paracetamol 500 Mg')
        ->and((int) $second->distinct_doctors_count)->toBe(1)
        ->and((int) $second->total_proposals_count)->toBe(1);

    // Summary metrics verification
    $metrics = ProposalConsensusQuery::summaryMetrics(2026);
    expect($metrics['total_medicines'])->toBe(2)
        ->and($metrics['consensus_medicines'])->toBe(1)
        ->and($metrics['top_supported_count'])->toBe(2);
});

it('filters consensus by min_doctors and search term', function (): void {
    $p1 = Proposal::create(['doctor_name' => 'dr. A', 'proposal_date' => '2026-11-01', 'period_year' => 2026]);
    $p1->items()->create(['medicine_name' => 'Obat Ramai']);
    $p1->items()->create(['medicine_name' => 'Obat Sepi']);

    $p2 = Proposal::create(['doctor_name' => 'dr. B', 'proposal_date' => '2026-11-02', 'period_year' => 2026]);
    $p2->items()->create(['medicine_name' => 'Obat Ramai']);

    // min_doctors = 2 only returns Obat Ramai
    $res = ProposalConsensusQuery::paginate(['min_doctors' => 2]);
    expect($res->items())->toHaveCount(1)
        ->and($res->items()[0]->medicine_name)->toBe('Obat Ramai');

    // search filter
    $searchRes = ProposalConsensusQuery::paginate(['search' => 'Sepi']);
    expect($searchRes->items())->toHaveCount(1)
        ->and($searchRes->items()[0]->medicine_name)->toBe('Obat Sepi');
});

it('handles empty database for summary metrics safely', function (): void {
    $metrics = ProposalConsensusQuery::summaryMetrics(2026);
    expect($metrics['total_medicines'])->toBe(0)
        ->and($metrics['consensus_medicines'])->toBe(0)
        ->and($metrics['top_supported_count'])->toBe(0);
});
