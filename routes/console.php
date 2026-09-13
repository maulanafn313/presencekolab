<?php

use App\Jobs\CreateDatabaseBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CreateDatabaseBackup)->dailyAt('02:00')
    ->timezone('Asia/Jakarta')->withoutOverlapping()
    ->when(fn () => config('backup.auto_enabled'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
