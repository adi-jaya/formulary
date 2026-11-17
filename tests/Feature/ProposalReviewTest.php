<?php

declare(strict_types=1);

use App\Models\Proposal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('formulary.review_secret', 'kft-secret-2026');
});

it('authenticates KFT session with valid passcode and rejects invalid or empty secret', function (): void {
    // 1. Empty secret payload rejected with 422
    $responseEmpty = $this->postJson(route('recap.auth'), [
        'secret' => '',
    ]);
    $responseEmpty->assertStatus(422)
        ->assertJsonValidationErrors(['secret']);

    // 2. Wrong passcode rejected with 422
    $responseWrong = $this->postJson(route('recap.auth'), [
        'secret' => 'wrong-passcode',
    ]);
    $responseWrong->assertStatus(422)
        ->assertJsonValidationErrors(['secret']);

    // 3. Empty configured secret in config rejected with 422
    config()->set('formulary.review_secret', '');
    $responseNoConfig = $this->postJson(route('recap.auth'), [
        'secret' => 'any-passcode',
    ]);
    $responseNoConfig->assertStatus(422)
        ->assertJsonValidationErrors(['secret']);

    config()->set('formulary.review_secret', 'kft-secret-2026');

    // 4. Valid secret with reviewer name
    $responseSuccess = $this->postJson(route('recap.auth'), [
        'secret' => 'kft-secret-2026',
        'reviewer_name' => 'apt. Siti Rahma, S.Farm',
    ]);
    $responseSuccess->assertOk()
        ->assertJson([
            'success' => true,
            'reviewer_name' => 'apt. Siti Rahma, S.Farm',
        ]);

    expect(session('kft_authenticated'))->toBeTrue()
        ->and(session('reviewer_name'))->toBe('apt. Siti Rahma, S.Farm');

    // 5. Valid secret with default reviewer name when omitted
    $responseDefault = $this->postJson(route('recap.auth'), [
        'secret' => 'kft-secret-2026',
    ]);
    $responseDefault->assertOk()
        ->assertJson([
            'success' => true,
            'reviewer_name' => 'Tim Farmasi',
        ]);

    expect(session('kft_authenticated'))->toBeTrue()
        ->and(session('reviewer_name'))->toBe('Tim Farmasi');
});

it('denies unauthenticated review update requests with 403 forbidden', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create([
        'medicine_name' => 'Paracetamol 500mg',
    ]);

    $response = $this->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'approved',
    ]);

    $response->assertForbidden();
});

it('updates review status and notes when authenticated in KFT session', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create([
        'medicine_name' => 'Paracetamol 500mg',
    ]);

    $statuses = ['approved', 'rejected', 'substituted', 'pending'];

    foreach ($statuses as $status) {
        $response = $this->withSession([
            'kft_authenticated' => true,
            'reviewer_name' => 'apt. Siti Rahma',
        ])->patchJson(route('proposals.items.review', $item), [
            'review_status' => $status,
            'review_notes' => "Status diubah menjadi {$status}",
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'item' => [
                    'id' => $item->id,
                    'review_status' => $status,
                    'review_notes' => "Status diubah menjadi {$status}",
                    'reviewer_name' => 'apt. Siti Rahma',
                ],
            ]);

        expect($item->fresh()->review_status)->toBe($status)
            ->and($item->fresh()->reviewed_at)->not->toBeNull()
            ->and($item->fresh()->reviewer_name)->toBe('apt. Siti Rahma');
    }
});

it('validates maximum 2000 characters for review_notes and allowed values for review_status', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create([
        'medicine_name' => 'Paracetamol 500mg',
    ]);

    // Invalid status
    $responseInvalidStatus = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'Tim Farmasi',
    ])->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'invalid_status',
    ]);
    $responseInvalidStatus->assertStatus(422)
        ->assertJsonValidationErrors(['review_status']);

    // Missing status
    $responseMissingStatus = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'Tim Farmasi',
    ])->patchJson(route('proposals.items.review', $item), []);
    $responseMissingStatus->assertStatus(422)
        ->assertJsonValidationErrors(['review_status']);

    // Notes exceeds 2000 characters
    $responseTooLong = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'Tim Farmasi',
    ])->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'approved',
        'review_notes' => str_repeat('A', 2001),
    ]);
    $responseTooLong->assertStatus(422)
        ->assertJsonValidationErrors(['review_notes']);

    // Notes exactly 2000 characters passes
    $responseExact2000 = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'Tim Farmasi',
    ])->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'approved',
        'review_notes' => str_repeat('A', 2000),
    ]);
    $responseExact2000->assertOk();
    expect(strlen($item->fresh()->review_notes))->toBe(2000);
});

it('normalizes excessive whitespace in review_notes and saves empty notes as null', function (): void {
    $proposal = Proposal::create([
        'doctor_name' => 'dr. Budi',
        'proposal_date' => '2026-11-17',
        'period_year' => 2026,
    ]);
    $item = $proposal->items()->create([
        'medicine_name' => 'Paracetamol 500mg',
    ]);

    // Collapses whitespace
    $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'Tim Farmasi',
    ])->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'approved',
        'review_notes' => "  Disetujui   dengan \n  catatan  khusus  ",
    ])->assertOk();

    expect($item->fresh()->review_notes)->toBe('Disetujui dengan catatan khusus');

    // Blank string saved as null
    $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'Tim Farmasi',
    ])->patchJson(route('proposals.items.review', $item), [
        'review_status' => 'pending',
        'review_notes' => '   ',
    ])->assertOk();

    expect($item->fresh()->review_notes)->toBeNull();
});

it('handles KFT logout by clearing session variables', function (): void {
    $response = $this->withSession([
        'kft_authenticated' => true,
        'reviewer_name' => 'apt. Siti Rahma',
    ])->postJson(route('recap.logout'));

    $response->assertOk()
        ->assertJson(['success' => true]);

    expect(session('kft_authenticated'))->toBeNull()
        ->and(session('reviewer_name'))->toBeNull();
});
