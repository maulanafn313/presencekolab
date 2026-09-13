<?php

namespace App\Jobs;

use App\Services\DatabaseBackup;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class CreateDatabaseBackup implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 86400;

    public int $timeout = 300;

    public int $tries = 3;

    public function uniqueId(): string
    {
        return date('Y-m-d');
    }

    public function handle(DatabaseBackup $backup): void
    {
        $key = 'backup:completed:'.date('Y-m-d');
        if (Cache::has($key)) {
            return;
        }
        $backup->create();
        Cache::put($key, true, now()->endOfDay());
    }
}
