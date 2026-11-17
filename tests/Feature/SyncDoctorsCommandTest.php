<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Cache::flush();
    Storage::fake('local');
});

it('synchronizes doctors from api and displays preview table on success', function (): void {
    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => [[
                'kdDokter' => '101',
                'namaDokter' => 'dr. Zaidan Sp.A',
                'isAktif' => true,
                'poli' => [['kdPoli' => 'ANA', 'namaPoli' => 'Klinik Anak']],
            ]],
        ], 200),
    ]);

    $this->artisan('formulary:sync-doctors')
        ->expectsOutputToContain('Sinkronisasi data dokter berhasil')
        ->expectsOutputToContain('dr. Zaidan Sp.A')
        ->assertSuccessful();

    expect(Storage::disk('local')->exists('doctors_snapshot.json'))->toBeTrue();
});

it('displays up to 5 doctors in preview table and notes remaining count', function (): void {
    $doctorsData = [];
    for ($i = 1; $i <= 7; $i++) {
        $doctorsData[] = [
            'kdDokter' => (string) (100 + $i),
            'namaDokter' => "dr. Dokter Ke-{$i} Sp.A",
            'isAktif' => true,
            'poli' => [['kdPoli' => 'ANA', 'namaPoli' => 'Klinik Anak']],
        ];
    }

    Http::fake([
        '*' => Http::response([
            'success' => true,
            'data' => $doctorsData,
        ], 200),
    ]);

    $this->artisan('formulary:sync-doctors')
        ->expectsOutputToContain('Sinkronisasi data dokter berhasil: 7 dokter aktif')
        ->expectsOutputToContain('dr. Dokter Ke-1 Sp.A')
        ->expectsOutputToContain('dr. Dokter Ke-5 Sp.A')
        ->expectsOutputToContain('... dan 2 dokter lainnya.')
        ->assertSuccessful();
});

it('handles api failure gracefully without corrupting local snapshot and exits with failure', function (): void {
    $existingSnapshot = [
        ['name' => 'dr. Existing Doctor Sp.B', 'clinic' => 'Klinik Bedah'],
    ];
    Storage::disk('local')->put('doctors_snapshot.json', json_encode($existingSnapshot, JSON_THROW_ON_ERROR));

    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $this->artisan('formulary:sync-doctors')
        ->expectsOutputToContain('Gagal menghubungi API Portal Sifast')
        ->assertFailed();

    $snapshotContent = Storage::disk('local')->get('doctors_snapshot.json');
    expect(json_decode((string) $snapshotContent, true))->toBe($existingSnapshot);
});

it('registers backup and sync doctors commands in the console schedule', function (): void {
    $schedule = app(Schedule::class);

    $events = collect($schedule->events());
    $commands = $events->map(fn ($event) => $event->command);

    expect($commands->some(fn ($cmd) => str_contains((string) $cmd, 'formulary:backup')))->toBeTrue()
        ->and($commands->some(fn ($cmd) => str_contains((string) $cmd, 'formulary:sync-doctors')))->toBeTrue();

    $backupEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'formulary:backup'));
    expect($backupEvent)->not->toBeNull()
        ->and($backupEvent->expression)->toBe('0 2 * * *')
        ->and($backupEvent->runInBackground)->toBeTrue();

    $syncEvent = $events->first(fn ($event) => str_contains((string) $event->command, 'formulary:sync-doctors'));
    expect($syncEvent)->not->toBeNull()
        ->and($syncEvent->expression)->toBe('0 6,18 * * *')
        ->and($syncEvent->runInBackground)->toBeTrue();
});
