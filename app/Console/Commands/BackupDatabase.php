<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'absen:backup {--prune : Apply configured retention after a successful backup}';

    protected $description = 'Create a private SQL backup and SHA-256 checksum';

    public function handle(DatabaseBackup $backup): int
    {
        $result = $backup->create();
        if ($this->option('prune')) {
            $backup->prune(dirname($result['path']));
        }
        $this->info($result['path']);
        $this->line('SHA256: '.$result['sha256']);

        return self::SUCCESS;
    }
}
