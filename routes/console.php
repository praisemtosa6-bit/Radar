<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('gigs:scrape-nitter')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('opportunities:scrape')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('opportunities:scrape-feeds')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
