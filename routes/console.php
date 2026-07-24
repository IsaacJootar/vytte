<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Email scheduled reports to recipients whose cadence is due. Runs hourly; the command
// itself decides which schedules are due, so the exact tick does not matter.
Schedule::command('reports:send-scheduled')->hourly();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('vytte:preflight', function () {
    $checks = [
        'APP_ENV is set' => filled(config('app.env')),
        'APP_KEY is set' => filled(config('app.key')),
        'APP_URL is set' => filled(config('app.url')),
        'Database connection is configured' => filled(config('database.default')),
        'Queue connection is configured' => filled(config('queue.default')),
        'Mail transport is configured' => filled(config('mail.default')),
        'Filesystem disk is configured' => filled(config('filesystems.default')),
    ];

    if (app()->environment('production')) {
        $checks['APP_DEBUG is false in production'] = config('app.debug') === false;
        $checks['APP_URL is not localhost in production'] = ! str_contains((string) config('app.url'), 'localhost');
    }

    foreach ($checks as $label => $passed) {
        $this->{$passed ? 'info' : 'error'}(($passed ? 'PASS ' : 'FAIL ').$label);
    }

    return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
})->purpose('Validate Vytte environment readiness before beta or production release');
