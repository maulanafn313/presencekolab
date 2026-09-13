<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseRestoreCommand extends Command
{
    protected $signature = 'absen:db-restore {--file= : Spesifik nama file backup} {--force : Paksa restore tanpa konfirmasi}';
    protected $description = 'Memulihkan (restore) database dari backup SQL terbaru atau spesifik.';

    public function handle()
    {
        $backupDir = storage_path('app/private/database-backups');
        if (!File::exists($backupDir)) {
            $this->error("Direktori backup tidak ditemukan: {$backupDir}");
            return 1;
        }

        $files = File::files($backupDir);
        $sqlFiles = array_filter($files, fn($file) => $file->getExtension() === 'sql');

        if (empty($sqlFiles)) {
            $this->error("Tidak ada file backup SQL ditemukan di: {$backupDir}");
            return 1;
        }

        $fileToRestore = null;
        if ($fileName = $this->option('file')) {
            $path = $backupDir . '/' . $fileName;
            if (!File::exists($path)) {
                $this->error("File backup tidak ditemukan: {$path}");
                return 1;
            }
            $fileToRestore = $path;
        } else {
            // Sort to find the latest
            usort($sqlFiles, fn($a, $b) => $b->getMTime() <=> $a->getMTime());
            $fileToRestore = $sqlFiles[0]->getPathname();
        }

        $this->info("Menemukan backup: " . basename($fileToRestore));

        if (!$this->option('force')) {
            if (!$this->confirm('PERINGATAN: Tindakan ini akan MENIMPA (overwrite) data saat ini. Apakah Anda yakin ingin melanjutkan?')) {
                $this->info('Restore dibatalkan.');
                return 0;
            }
        }

        $this->info('Memulai restore database...');
        try {
            $sql = File::get($fileToRestore);
            DB::unprepared($sql);
            $this->info('Restore database berhasil diselesaikan!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Gagal memulihkan database: ' . $e->getMessage());
            return 1;
        }
    }
}
