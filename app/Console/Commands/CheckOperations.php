<?php

namespace App\Console\Commands;

use App\Legacy\Paths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class CheckOperations extends Command
{
    protected $signature = 'absen:health {--python : Check Python runtime and imports}';

    protected $description = 'Read-only database, backup freshness, failed queue jobs and Python checks';

    public function handle(): int
    {
        $ok = true;
        try {
            DB::select('SELECT 1');
            $failed = DB::table('failed_jobs')->count();
            $pending = DB::table('jobs')->count();
            $this->line('database=ok pending_jobs='.$pending.' failed_jobs='.$failed);
            if ($failed > 0) {
                $ok = false;
            }
        } catch (\Throwable $e) {
            report($e);
            $this->error('database=unavailable');
            $ok = false;
        }
        $files = glob(Paths::backups().'/absen_db_backup_*.sql') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0] ?? null;
        $valid = $latest && is_file($latest.'.sha256') && hash_equals(trim(file_get_contents($latest.'.sha256')), hash_file('sha256', $latest));
        $fresh = $valid && time() - filemtime($latest) < 172800;
        $this->line('backup='.($fresh ? 'ok' : 'missing_stale_or_invalid').' recovery_points='.count($files));
        if (! $fresh) {
            $ok = false;
        }
        if ($this->option('python')) {
            try {
                $runtime = app(\App\Services\FaceNetProcess::class);
                $process = $runtime->runtime(['-c', 'import asyncio, socket, torch, cv2, numpy, facenet_pytorch, mysql.connector; print("imports=ok")']);
                $process->setTimeout(config('facenet.timeout'));
                $process->mustRun();
                $dependencies = $runtime->runtime(['-m', 'pip', 'check']);
                $dependencies->setTimeout(config('facenet.timeout'));
                $dependencies->mustRun();
                $model = is_file(config('facenet.model_path'));
                $this->line('python=ok model='.($model ? 'present' : 'missing'));
                if (! $model) {
                    $ok = false;
                }
            } catch (\Throwable $e) {
                report($e);
                $this->error('python=unavailable_or_missing_dependencies');
                $ok = false;
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
