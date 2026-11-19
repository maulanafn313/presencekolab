<?php

namespace App\Console\Commands;

use App\Services\Attendance\FridayStatusCorrection;
use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

class FixFridayStatus extends Command
{
    protected $signature = 'attendance:fix-friday-status {--apply : Ubah data setelah membuat backup}';

    protected $description = 'Pratinjau atau koreksi status presensi Jumat sejak 7 Juli 2026 (batas masuk 07:30)';

    public function handle(DatabaseBackup $backup): int
    {
        $rows = FridayStatusCorrection::pending();
        if ($rows->isEmpty()) {
            $this->info('Tidak ada status Jumat yang perlu dikoreksi.');

            return self::SUCCESS;
        }

        $this->table(['Tanggal', 'Jam Masuk', 'Nama', 'Status Sekarang', 'Menjadi'], $rows->map(fn ($row) => [
            substr($row->jam_masuk_iso, 0, 10), substr($row->jam_masuk_iso, 11, 5), $row->nama, $row->status, $row->expected,
        ]));
        $toLate = $rows->where('expected', 'terlambat')->count();
        $this->line("Total {$rows->count()} data: $toLate menjadi terlambat, ".($rows->count() - $toLate).' menjadi ontime.');

        if (! $this->option('apply')) {
            $this->warn('Pratinjau saja, data belum diubah. Tambahkan --apply untuk menerapkan.');

            return self::SUCCESS;
        }

        $file = $backup->create()['filename'];
        $this->info("Backup dibuat: $file");
        $this->info(FridayStatusCorrection::apply($rows).' data berhasil dikoreksi.');

        return self::SUCCESS;
    }
}
