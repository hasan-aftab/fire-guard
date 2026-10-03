<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('fire-tracker:prune-files')->hourly()->withoutOverlapping(60);
Schedule::command('fire-tracker:send-reminders')
    ->dailyAt(config('fire-tracker.reminders.send_at'))
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(60);
