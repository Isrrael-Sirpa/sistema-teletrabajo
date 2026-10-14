<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$deadlineReminderHour = max(0, min(23, (int) config('documents.deadlines.reminder_hour', 8)));

Schedule::command('documents:process-deadlines')
    ->dailyAt(sprintf('%02d:00', $deadlineReminderHour))
    ->timezone((string) config('app.timezone'))
    ->withoutOverlapping();
