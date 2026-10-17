<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('licensevault:generate-reminders')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('licensevault:backup')
    ->dailyAt(config('licensevault.backup.time', '03:00'))
    ->withoutOverlapping()
    ->when(fn () => mb_strlen((string) config('licensevault.backup.passphrase')) >= 12);
