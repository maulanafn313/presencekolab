<?php

// CLI-only integration check. Never migrates, restores, or clears the application's DB.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Services\Attendance\AttendanceSubmission;
use App\Services\DatabaseBackup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

$base = config('database.connections.mysql');
$production = $base['database'];
$worker = ($argv[1] ?? '') === 'worker';
$source = $worker ? ($argv[2] ?? '') : 'absenv_'.bin2hex(random_bytes(6));
$restore = $source.'_restore';
if (! preg_match('/^absenv_[a-f0-9]{12}$/D', $source) || $source === $production || $restore === $production) {
    throw new RuntimeException('Unsafe verification database name.');
}
config(['database.default' => 'verification', 'database.connections.verification' => array_replace($base, ['database' => $source]),
    'app.env' => 'testing', 'backup.auto_enabled' => false, 'session.driver' => 'array', 'cache.default' => 'array']);
Carbon::setTestNow(Carbon::parse('2026-09-14 07:30:00', 'Asia/Jakarta'));
$app->instance('env', 'testing');

if ($worker) {
    $result = app(AttendanceSubmission::class)->submit([
        'nim' => 'VERIFY-1', 'mode' => 'masuk', 'lat' => '-6.975', 'lng' => '107.630', 'lokasi' => 'Kantor', 'gps_accuracy' => 10, 'request_id' => 'concurrent-check',
    ], ['id' => 1, 'role' => 'pegawai', 'nim' => 'VERIFY-1']);
    echo $result->getContent();
    exit;
}

$admin = DB::connection('mysql');
$created = [];
$failed = false;
$workers = [];
$temporary = storage_path('framework/testing/'.$source);
try {
    foreach ([$source, $restore] as $name) {
        // No IF NOT EXISTS: a collision must fail instead of using an existing database.
        $admin->statement('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
        $created[] = $name;
    }
    $status = Artisan::call('migrate', ['--database' => 'verification', '--force' => true]);
    if ($status !== 0) {
        throw new RuntimeException(Artisan::output());
    }
    echo "MYSQL_CLEAN_MIGRATION=PASS\n";
    DB::table('users')->insert(['id' => 1, 'role' => 'pegawai', 'nim' => 'VERIFY-1', 'nama' => 'Verification Fixture', 'email' => 'verification@example.test', 'password' => 'fixture']);
    DB::table('settings')->insert([
        ['setting_key' => 'wfo_mode', 'setting_value' => 'coordinate'], ['setting_key' => 'wfo_lat', 'setting_value' => '-6.975'],
        ['setting_key' => 'wfo_lng', 'setting_value' => '107.630'], ['setting_key' => 'wfo_radius', 'setting_value' => '200'],
    ]);
    $workers = [new Process([PHP_BINARY, __FILE__, 'worker', $source]), new Process([PHP_BINARY, __FILE__, 'worker', $source])];
    foreach ($workers as $process) {
        $process->setTimeout(60);
        $process->start();
    }
    foreach ($workers as $process) {
        $process->wait();
        $result = json_decode($process->getOutput(), true);
        if (! $process->isSuccessful() || empty($result['ok'])) {
            throw new RuntimeException('Concurrent submission failed: '.$process->getOutput());
        }
    }
    if (DB::table('attendance')->count() !== 1 || DB::table('attendance_submissions')->count() !== 1) {
        throw new RuntimeException('Duplicate concurrent attendance.');
    }
    echo "MYSQL_CONCURRENT_RETRY=PASS\n";

    $backup = app(DatabaseBackup::class)->create(DB::connection(), $temporary);
    if (! hash_equals($backup['sha256'], hash_file('sha256', $backup['path']))) {
        throw new RuntimeException('Checksum failed.');
    }
    config(['database.connections.verification_restore' => array_replace($base, ['database' => $restore])]);
    DB::connection('verification_restore')->unprepared(file_get_contents($backup['path']));
    if (DB::connection('verification_restore')->table('attendance')->count() !== 1) {
        throw new RuntimeException('Restore failed.');
    }
    if (DB::connection('verification_restore')->table('users')->value('nim') !== 'VERIFY-1') {
        throw new RuntimeException('Restore mismatch.');
    }
    echo "MYSQL_BACKUP_RESTORE=PASS\n";
} catch (Throwable $e) {
    $failed = true;
    fwrite(STDERR, 'VERIFICATION_FAILED: '.$e->getMessage().PHP_EOL);
} finally {
    foreach ($workers as $process) {
        if ($process->isRunning()) {
            $process->stop();
        }
    }
    DB::purge('verification');
    DB::purge('verification_restore');
    foreach ($created as $name) {
        if (! preg_match('/^absenv_[a-f0-9]{12}(_restore)?$/D', $name) || $name === $production) {
            throw new RuntimeException('Unsafe cleanup target.');
        }
        $admin->statement('DROP DATABASE `'.$name.'`');
    }
    // This path is uniquely generated inside framework/testing; contains fixture SQL only.
    File::deleteDirectory($temporary);
    echo "ISOLATED_DATABASES_CLEANED\n";
}
exit($failed ? 1 : 0);
