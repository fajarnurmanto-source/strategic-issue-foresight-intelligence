<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// At most one explicitly opted-in social watch scan per eight-hour tick.
// Requires an operator-controlled local Laravel scheduler; no automatic production activation.
Schedule::command('sifi:social-watch:tick')->everyEightHours()->withoutOverlapping(30);
