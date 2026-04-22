<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gigs:scrape-nitter')
    ->everyFiveMinutes()
    ->withoutOverlapping()   // prevents stacking if the previous run hasn't finished
    ->runInBackground();     // runs without blocking other scheduled tasks
