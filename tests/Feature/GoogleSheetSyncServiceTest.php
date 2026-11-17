<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Services\GoogleSheetSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('pushes new proposal rows with 12 canonical columns to webhook', function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');

    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'success', 'count' => 1], 200),
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Agus Sp.B',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
        'notes' => 'Catatan dokter',
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Ceftriaxone 1g Inj',
        'principal_name' => 'Bernofarm',
        'clinical_justification' => 'Antibiotik profilaksis',
    ]);

    $service = app(GoogleSheetSyncService::class);
    $result = $service->pushNewProposal($proposal);

    expect($result)->toBeTrue();

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['secret'] === 'secret123'
            && $body['action'] === 'append_rows'
            && count($body['rows']) === 1
            && $body['rows'][0]['no'] === 1
            && $body['rows'][0]['proposal_date'] === '17/11/2026'
            && $body['rows'][0]['period_year'] === 2026
            && $body['rows'][0]['doctor_name'] === 'dr. Agus Sp.B'
            && $body['rows'][0]['medicine_name'] === 'Ceftriaxone 1g Inj'
            && $body['rows'][0]['principal_name'] === 'Bernofarm'
            && $body['rows'][0]['clinical_justification'] === 'Antibiotik profilaksis'
            && $body['rows'][0]['notes'] === 'Catatan dokter'
            && $body['rows'][0]['review_status'] === 'pending'
            && $body['rows'][0]['review_notes'] === '-'
            && $body['rows'][0]['reviewer_name'] === '-';
    });
});

it('gracefully handles http failures and empty webhook url without throwing exceptions', function (): void {
    config()->set('formulary.sheet_webhook_url', '');

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Agus Sp.B',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);

    $service = app(GoogleSheetSyncService::class);
    expect($service->pushNewProposal($proposal))->toBeFalse();

    // URL configured, but HTTP error response (500)
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    Http::fake([
        'https://script.google.com/*' => Http::response(['error' => 'Internal Server Error'], 500),
    ]);

    $proposal->items()->create([
        'medicine_name' => 'Paracetamol 500mg',
    ]);

    expect($service->pushNewProposal($proposal))->toBeFalse();

    // Network connection exception
    Http::fake([
        'https://script.google.com/*' => function () {
            throw new ConnectionException('Connection timed out');
        },
    ]);

    expect($service->pushNewProposal($proposal))->toBeFalse();
});

it('fails and logs warning when webhook returns 200 with error status payload', function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');

    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'error', 'message' => 'Unauthorized'], 200),
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Agus Sp.B',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Ceftriaxone 1g Inj',
    ]);

    $service = app(GoogleSheetSyncService::class);
    expect($service->pushNewProposal($proposal))->toBeFalse();
});

it('tests syncAll sending chunks and triggering clear_sheet when clearFirst is true', function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');

    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'success'], 200),
    ]);

    $p1 = Proposal::create([
        'doctor_name' => 'dr. Rudi Sp.PD',
        'proposal_date' => '2026-11-15',
        'period_year' => 2026,
    ]);
    $p1->items()->create([
        'medicine_name' => 'Metformin 500mg',
        'principal_name' => 'Dexa',
        'clinical_justification' => 'Diabetes',
    ]);

    $p2 = Proposal::create([
        'doctor_name' => 'dr. Maya Sp.A',
        'proposal_date' => '2026-11-16',
        'period_year' => 2026,
    ]);
    $p2->items()->create([
        'medicine_name' => 'Amoxicillin Sirup',
    ]);

    $service = app(GoogleSheetSyncService::class);
    $totalSent = $service->syncAll(2026, clearFirst: true);

    expect($totalSent)->toBe(2);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['secret'] === 'secret123' && $body['action'] === 'clear_sheet';
    });

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['secret'] === 'secret123'
            && $body['action'] === 'append_rows'
            && count($body['rows']) === 2;
    });
});

it('returns 0 in syncAll when sheet_webhook_url is empty', function (): void {
    config()->set('formulary.sheet_webhook_url', '');

    $service = app(GoogleSheetSyncService::class);
    expect($service->syncAll())->toBe(0);
});

it('dispatches deferred sync when proposal is created via ProposalController store', function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');

    Http::fake([
        'https://portalsifast.rsaisyiyahsitifatimah.com/*' => Http::response(['success' => true, 'data' => []], 200),
        'https://script.google.com/*' => Http::response(['status' => 'success'], 200),
    ]);

    $payload = [
        'doctor_name' => 'dr. Agus Sp.B',
        'proposal_date' => '2026-11-17',
        'notes' => 'Catatan dokter',
        'items' => [
            [
                'medicine_name' => 'Ceftriaxone 1g Inj',
                'principal_name' => 'Bernofarm',
                'clinical_justification' => 'Antibiotik profilaksis',
            ],
        ],
    ];

    $this->post(route('proposals.store'), $payload)
        ->assertRedirect(route('proposals.create'));

    Http::assertSent(function ($request) {
        $body = $request->data();

        return isset($body['action'])
            && $body['action'] === 'append_rows'
            && $body['rows'][0]['medicine_name'] === 'Ceftriaxone 1g Inj';
    });
});
