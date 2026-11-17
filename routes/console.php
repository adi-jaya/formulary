<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('formulary:backup')
    ->dailyAt('02:00')
    ->runInBackground();

Schedule::command('formulary:sync-doctors')
    ->twiceDaily(6, 18)
    ->runInBackground();
