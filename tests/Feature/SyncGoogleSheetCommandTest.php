<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');
});

it('aborts bulk sync when confirmation is rejected without clear option', function (): void {
    Http::fake();

    $this->artisan('formulary:sync-sheets')
        ->expectsConfirmation('Perhatian: Sinkronisasi tanpa --clear akan menambahkan seluruh baris ke bawah lembar kerja. Lanjutkan?', 'no')
        ->expectsOutputToContain('Sinkronisasi dibatalkan.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('proceeds when confirmation is accepted without clear option', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'success'], 200),
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi Sp.A',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Paracetamol 500mg',
    ]);

    $this->artisan('formulary:sync-sheets')
        ->expectsConfirmation('Perhatian: Sinkronisasi tanpa --clear akan menambahkan seluruh baris ke bawah lembar kerja. Lanjutkan?', 'yes')
        ->expectsOutputToContain('Sinkronisasi massal ke Google Sheets berhasil')
        ->assertSuccessful();

    Http::assertNotSent(function ($request) {
        return $request->data()['action'] === 'clear_sheet';
    });

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['secret'] === 'secret123'
            && $body['action'] === 'append_rows'
            && count($body['rows']) === 1
            && $body['rows'][0]['medicine_name'] === 'Paracetamol 500mg';
    });
});

it('synchronizes all proposals to google sheets with clear option', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'success'], 200),
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Hasan Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Amoxicillin 500mg',
    ]);

    $this->artisan('formulary:sync-sheets --clear')
        ->expectsOutputToContain('Sinkronisasi massal ke Google Sheets berhasil')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        return $request->data()['action'] === 'clear_sheet';
    });

    Http::assertSent(function ($request) {
        return $request->data()['action'] === 'append_rows';
    });
});

it('filters synchronization by year option', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'success'], 200),
    ]);

    $p2025 = Proposal::create([
        'doctor_name' => 'dr. Senior Sp.A',
        'proposal_date' => '2025-11-17',
        'period_year' => 2025,
    ]);
    $p2025->items()->create(['medicine_name' => 'Obat 2025']);

    $p2026 = Proposal::create([
        'doctor_name' => 'dr. Junior Sp.A',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $p2026->items()->create(['medicine_name' => 'Obat 2026']);

    $this->artisan('formulary:sync-sheets --clear --year=2026')
        ->expectsOutputToContain('Sinkronisasi massal ke Google Sheets berhasil: 1 baris usulan terkirim.')
        ->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = $request->data();
        if ($body['action'] !== 'append_rows') {
            return false;
        }

        return count($body['rows']) === 1
            && $body['rows'][0]['medicine_name'] === 'Obat 2026'
            && $body['rows'][0]['period_year'] === 2026;
    });
});

it('fails with warning message when FORMULARY_SHEET_WEBHOOK_URL is empty', function (): void {
    Http::fake();
    config()->set('formulary.sheet_webhook_url', '');

    $this->artisan('formulary:sync-sheets --clear')
        ->expectsOutputToContain('FORMULARY_SHEET_WEBHOOK_URL')
        ->assertFailed();

    Http::assertNothingSent();
});

it('fails with warning when webhook returns error during bulk sync', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::response(['status' => 'error', 'message' => 'Unauthorized'], 200),
    ]);

    $proposal = Proposal::create([
        'doctor_name' => 'dr. Hasan Sp.PD',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $proposal->items()->create([
        'medicine_name' => 'Amoxicillin 500mg',
    ]);

    $this->artisan('formulary:sync-sheets --clear')
        ->expectsOutputToContain('Peringatan: Tidak ada baris usulan yang berhasil dikirim')
        ->assertFailed();
});
