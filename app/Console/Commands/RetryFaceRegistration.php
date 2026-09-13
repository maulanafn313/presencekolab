<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FaceRegistration;
use Illuminate\Console\Command;

class RetryFaceRegistration extends Command
{
    protected $signature = 'absen:face-retry {user : User ID}';

    protected $description = 'Ulangi embedding foto user tersimpan tanpa membuat akun baru';

    public function handle(FaceRegistration $registration): int
    {
        $user = User::findOrFail($this->argument('user'));
        try {
            $ready = $registration->generate($user);
        } catch (\Throwable $e) {
            report($e);
            $ready = false;
        }
        $this->line($ready ? 'Wajah siap digunakan.' : 'Embedding gagal. Periksa foto, runtime Python, dan model.');

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
