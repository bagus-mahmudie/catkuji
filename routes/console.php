<?php

use App\Models\GachaCategory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('shipments:sync-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => GachaCategory::archiveExpiredPromos())
    ->name('archive-expired-promos')->everyMinute()->withoutOverlapping();
