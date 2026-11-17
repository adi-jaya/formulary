<?php

declare(strict_types=1);

use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [[
                'kdDokter' => '378',
                'namaDokter' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
                'isAktif' => true,
                'poli' => [['kdPoli' => 'URO', 'namaPoli' => 'Klinik Spesialis Urologi']],
            ]],
        ], 200),
    ]);
});

it('renders the proposal form with today default date', function (): void {
    $this->get(route('proposals.create'))
        ->assertOk()
        ->assertSee('Formulir Usulan Obat Formularium')
        ->assertSee('Nama Dokter')
        ->assertSee(now()->toDateString());
});

it('stores proposal header and multiple items atomically, normalizing spaces and dash-only principals', function (): void {
    $payload = [
        'doctor_name' => '  dr. Budi   Santoso Sp.An ',
        'proposal_date' => '2026-11-15',
        'notes' => '  Catatan   khusus ketersediaan distributor  ',
        'items' => [
            [
                'medicine_name' => '  Diarvit   100 Mg Tab ',
                'principal_name' => '  Novel  ',
                'clinical_justification' => '  Efikasi   tinggi untuk kasus akut  ',
            ],
            [
                'medicine_name' => 'Paracetamol   Drop',
                'principal_name' => ' - ',
                'clinical_justification' => '   ',
            ],
            [
                'medicine_name' => 'Ketamin Inj',
                'principal_name' => '',
                'clinical_justification' => null,
            ],
        ],
    ];

    $this->post(route('proposals.store'), $payload)
        ->assertRedirect(route('proposals.create'))
        ->assertSessionHas('submitted_summary');

    $proposal = Proposal::with('items')->sole();

    expect($proposal->doctor_name)->toBe('dr. Budi Santoso Sp.An')
        ->and($proposal->proposal_date->toDateString())->toBe('2026-11-15')
        ->and($proposal->period_year)->toBe(2026)
        ->and($proposal->notes)->toBe('Catatan khusus ketersediaan distributor')
        ->and($proposal->items)->toHaveCount(3)
        ->and($proposal->items[0]->medicine_name)->toBe('Diarvit 100 Mg Tab')
        ->and($proposal->items[0]->principal_name)->toBe('Novell')
        ->and($proposal->items[0]->clinical_justification)->toBe('Efikasi tinggi untuk kasus akut')
        ->and($proposal->items[1]->principal_name)->toBeNull()
        ->and($proposal->items[1]->clinical_justification)->toBeNull()
        ->and($proposal->items[2]->principal_name)->toBeNull()
        ->and($proposal->items[2]->clinical_justification)->toBeNull();
});

it('prevents duplicate double-submit of identical payload within 15 seconds', function (): void {
    $payload = [
        'doctor_name' => 'dr. Ahmad Nadhir Sp. U. M. Ked. Klin',
        'proposal_date' => '2026-11-03',
        'items' => [
            ['medicine_name' => 'Diarvit 100 Mg Tab', 'principal_name' => 'Novell'],
        ],
    ];

    $this->post(route('proposals.store'), $payload)->assertRedirect(route('proposals.create'));
    $this->post(route('proposals.store'), $payload)->assertRedirect(route('proposals.create'));

    expect(Proposal::count())->toBe(1)
        ->and(ProposalItem::count())->toBe(1);
});

it('preserves submitted repeater rows in session old input when validation fails', function (): void {
    $response = $this->from(route('proposals.create'))->post(route('proposals.store'), [
        'doctor_name' => '',
        'proposal_date' => '2026-11-03',
        'notes' => 'Catatan penting yang harus tetap ada',
        'items' => [
            [
                'medicine_name' => 'Obat Satu 10 Mg',
                'principal_name' => 'Sanbe',
                'clinical_justification' => 'Alasan klinis obat satu',
            ],
            [
                'medicine_name' => 'Obat Dua 20 Mg',
                'principal_name' => 'Kalbe',
                'clinical_justification' => 'Alasan klinis obat dua',
            ],
        ],
    ]);

    $response->assertRedirect(route('proposals.create'))
        ->assertSessionHasErrors(['doctor_name']);

    $this->followRedirects($response)
        ->assertSee('Obat Satu 10 Mg')
        ->assertSee('Obat Dua 20 Mg')
        ->assertSee('Catatan penting yang harus tetap ada')
        ->assertSee('Alasan klinis obat satu')
        ->assertSee('Alasan klinis obat dua');
});

it('validates maximum 2000 characters on notes and clinical justification', function (): void {
    $longText = str_repeat('a', 2001);

    $this->post(route('proposals.store'), [
        'doctor_name' => 'dr. Budi Sp.An',
        'proposal_date' => '2026-11-03',
        'notes' => $longText,
        'items' => [
            [
                'medicine_name' => 'Obat 10 Mg',
                'principal_name' => 'Sanbe',
                'clinical_justification' => $longText,
            ],
        ],
    ])->assertSessionHasErrors(['notes', 'items.0.clinical_justification']);
});

it('renders hydration json carrier with server flags for fresh load, validation errors, and flash success', function (): void {
    // 1. Fresh load
    $response = $this->get(route('proposals.create'))->assertOk();
    $content = $response->getContent();
    expect($content)->toContain('"hasOldInput":false')
        ->and($content)->toContain('"hasFlashSuccess":false');

    // 2. Validation error redirect
    $invalidResponse = $this->from(route('proposals.create'))->post(route('proposals.store'), [
        'doctor_name' => '',
        'proposal_date' => '2026-11-17',
        'items' => [['medicine_name' => 'Obat A', 'principal_name' => 'Sanbe']],
    ]);
    $invalidResponse->assertRedirect(route('proposals.create'));

    $followedInvalid = $this->followRedirects($invalidResponse);
    expect($followedInvalid->getContent())->toContain('"hasOldInput":true')
        ->and($followedInvalid->getContent())->toContain('"hasFlashSuccess":false');

    // 3. Successful submission redirect
    $validResponse = $this->from(route('proposals.create'))->post(route('proposals.store'), [
        'doctor_name' => 'dr. Nadhir Sp.U',
        'proposal_date' => '2026-11-17',
        'items' => [['medicine_name' => 'Obat B', 'principal_name' => 'Kalbe']],
    ]);
    $validResponse->assertRedirect(route('proposals.create'));

    $followedValid = $this->followRedirects($validResponse);
    expect($followedValid->getContent())->toContain('"hasFlashSuccess":true');
});

it('renders draft restoration banner, character counters, mobile floating bar, and shortcut handlers in proposal form', function (): void {
    $response = $this->get(route('proposals.create'))->assertOk();

    // 1. Draft restoration banner markup & button
    $response->assertSee('x-show="isDraftRestored"', false)
        ->assertSee('Memulihkan draf usulan belum terkirim dari penyimpanan perangkat Anda.')
        ->assertSee('@click="clearDraft()"', false);

    // 2. Form shortcut attributes and id
    $response->assertSee('id="proposal-form"', false)
        ->assertSee('@keydown.ctrl.enter.prevent="submitForm()"', false)
        ->assertSee('@keydown.meta.enter.prevent="submitForm()"', false);

    // 3. Medicine input autofocus attribute hook
    $response->assertSee('data-medicine-input', false);

    // 4. Character counters for clinical justification and notes
    $response->assertSee('(item.clinical_justification || \'\').length', false)
        ->assertSee('(notes || \'\').length', false)
        ->assertSee('/ 2.000 karakter', false);

    // 5. Mobile floating action bar with safe area inset and submit binding
    $response->assertSee('x-show="!isKeyboardVisible"', false)
        ->assertSee('env(safe-area-inset-bottom', false)
        ->assertSee('@click="submitForm()"', false);
});
