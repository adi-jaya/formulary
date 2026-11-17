<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('formulary.sheet_webhook_url', 'https://script.google.com/macros/s/test/exec');
    config()->set('formulary.sheet_webhook_secret', 'secret123');
});

it('fails with warning when sheet_webhook_url is empty', function (): void {
    config()->set('formulary.sheet_webhook_url', '');

    $this->artisan('formulary:test-webhook')
        ->expectsOutputToContain('URL webhook Google Sheets belum dikonfigurasi')
        ->assertFailed();
});

it('detects google login redirect and explains permission issue', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::response('', 302, [
            'Location' => 'https://accounts.google.com/ServiceLogin?continue=...',
        ]),
    ]);

    $this->artisan('formulary:test-webhook')
        ->expectsOutputToContain('Who has access')
        ->assertFailed();
});

it('detects 401 unauthorized from google edge server', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::response('<html>Unauthorized</html>', 401),
    ]);

    $this->artisan('formulary:test-webhook')
        ->expectsOutputToContain('HTTP 401')
        ->assertFailed();
});

it('detects secret mismatch when webhook responds with invalid secret error', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::sequence()
            ->push(['status' => 'ok', 'message' => 'active'], 200)
            ->push(['status' => 'error', 'message' => 'Unauthorized: Invalid secret'], 200),
    ]);

    $this->artisan('formulary:test-webhook')
        ->expectsOutputToContain('Secret tidak cocok')
        ->assertFailed();
});

it('succeeds when webhook is reachable and ping pong succeeds', function (): void {
    Http::fake([
        'https://script.google.com/*' => Http::sequence()
            ->push(['status' => 'ok', 'message' => 'active'], 200)
            ->push(['status' => 'success', 'message' => 'Pong'], 200),
    ]);

    $this->artisan('formulary:test-webhook')
        ->expectsOutputToContain('SUKSES')
        ->assertSuccessful();
});
