<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders review status badges and clinical transparency notes for all users without login', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Hasan Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);

    $proposal->items()->create([
        'medicine_name' => 'Cefixime 100mg',
        'review_status' => 'approved',
        'review_notes' => 'Disetujui untuk rawat jalan',
        'reviewer_name' => 'apt. Siti Rahma',
        'reviewed_at' => now(),
    ]);

    $proposal->items()->create([
        'medicine_name' => 'Meropenem 1g',
        'review_status' => 'rejected',
        'review_notes' => 'Ditolak karena belum ada PPAB',
    ]);

    $proposal->items()->create([
        'medicine_name' => 'Levofloxacin 500mg',
        'review_status' => 'substituted',
        'review_notes' => 'Diarahkan substitusi ke Ciprofloxacin',
    ]);

    $proposal->items()->create([
        'medicine_name' => 'Amoxicillin Sirup',
        'review_status' => 'pending',
    ]);

    $response = $this->get(route('proposals.index'));
    $response->assertOk()
        ->assertSee('Disetujui')
        ->assertSee('Disetujui untuk rawat jalan')
        ->assertSee('Ditolak')
        ->assertSee('Ditolak karena belum ada PPAB')
        ->assertSee('Substitusi')
        ->assertSee('Diarahkan substitusi ke Ciprofloxacin')
        ->assertSee('Menunggu')
        ->assertSee('Mode Telaah KFT')
        ->assertDontSee('Keluar Mode KFT')
        ->assertDontSee('Edit Telaah');
});

it('shows active KFT mode badge, reviewer name, and Keluar Mode KFT button when session is authenticated', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Fatimah Sp.A',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);

    $proposal->items()->create([
        'medicine_name' => 'Paracetamol Drop',
        'review_status' => 'pending',
    ]);

    $response = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'apt. Siti Rahma, S.Farm',
    ])->get(route('proposals.index'));

    $response->assertOk()
        ->assertSee('Mode KFT Aktif')
        ->assertSee('apt. Siti Rahma, S.Farm')
        ->assertSee('Keluar Mode KFT')
        ->assertSee('Edit Telaah')
        ->assertDontSee('Mode Telaah KFT');
});

it('filters recap items by review_status in ProposalRecapController', function (): void {
    $p1 = Proposal::create([
        'doctor_name' => 'dr. A Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $p1->items()->create([
        'medicine_name' => 'Obat Disetujui',
        'review_status' => 'approved',
    ]);

    $p2 = Proposal::create([
        'doctor_name' => 'dr. B Sp.B',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $p2->items()->create([
        'medicine_name' => 'Obat Menunggu',
        'review_status' => 'pending',
    ]);

    $responseApproved = $this->get(route('proposals.index', ['review_status' => 'approved']));
    $responseApproved->assertOk()
        ->assertSee('Obat Disetujui')
        ->assertDontSee('Obat Menunggu');

    $responsePending = $this->get(route('proposals.index', ['review_status' => 'pending']));
    $responsePending->assertOk()
        ->assertSee('Obat Menunggu')
        ->assertDontSee('Obat Disetujui');
});
