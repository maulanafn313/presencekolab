<?php

// Isolated probe for the reduced environment inherited from a Windows HTTP server.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$mode = $argv[1] ?? 'fixed';
$systemPath = getenv('PATH') ?: getenv('Path');
$_ENV = [];
$_SERVER = ['PATH'=>$systemPath, 'HTTP_HOST'=>'localhost'];
$arguments = ['-c', 'import asyncio, socket, torch; print("PYTHON_HTTP_ENVIRONMENT_OK")'];
$process = $mode === 'baseline'
    ? new \Symfony\Component\Process\Process(array_merge([config('facenet.python')], $arguments), base_path('scripts'), null, null, 60)
    : app(\App\Services\FaceNetProcess::class)->runtime($arguments);
$process->run();
echo $process->getOutput();
fwrite(STDERR, $process->getErrorOutput());
exit($process->getExitCode() ?? 1);
