<?php

namespace Tests\Feature;

use App\Services\FaceNetProcess;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FaceNetProcessTest extends TestCase
{
    public function test_runtime_keeps_os_environment_when_http_server_globals_are_reduced(): void
    {
        $previousServer = $_SERVER;
        $previousEnv = $_ENV;
        try {
            $_SERVER = ['HTTP_HOST'=>'localhost', 'HTTP_SYSTEMROOT'=>'untrusted-header'];
            $_ENV = [];
            $runtime = app(FaceNetProcess::class)->runtime(['-c', 'pass']);
            foreach (['SystemRoot','PATH','TEMP'] as $name) {
                $expected = getenv($name, true);
                if (is_string($expected) && $expected !== '') $this->assertSame($expected, $runtime->getEnv()[$name]);
            }
            $this->assertNotContains('untrusted-header', $runtime->getEnv());
            $this->assertSame((float)config('facenet.timeout'), $runtime->getTimeout());
        } finally {
            $_SERVER = $previousServer;
            $_ENV = $previousEnv;
        }
    }

    public function test_windows_python_imports_work_with_http_style_environment(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || !is_file(config('facenet.python'))) {
            $this->markTestSkipped('Requires the configured local Windows FaceNet environment.');
        }
        $probe = new Process([PHP_BINARY, base_path('tests/Fixtures/facenet_environment.php'), 'fixed'], base_path(), null, null, 60);
        $probe->mustRun();
        $this->assertStringContainsString('PYTHON_HTTP_ENVIRONMENT_OK', $probe->getOutput());
    }
}
