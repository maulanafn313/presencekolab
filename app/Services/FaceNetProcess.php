<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class FaceNetProcess
{
    public function make(string $json): Process
    {
        return $this->runtime([base_path('scripts/facenet_cli.py'), $json]);
    }

    /** Use the same runtime environment for HTTP requests and operational probes. */
    public function runtime(array $arguments): Process
    {
        $database = config('database.connections.'.config('database.default'));
        $pythonPath = array_filter([config('facenet.python_path'), base_path('scripts'), base_path('scripts/facenet-master/src')]);

        // Symfony intersects inherited env with $_SERVER. HTTP may contain only a
        // subset, dropping SystemRoot and breaking Python's Windows socket imports.
        // Copy only known OS variables, never request headers or request payloads.
        $operatingSystem = [];
        foreach (['SystemRoot', 'WINDIR', 'PATH', 'PATHEXT', 'COMSPEC', 'TEMP', 'TMP', 'USERPROFILE', 'LOCALAPPDATA', 'APPDATA'] as $key) {
            $value = getenv($key, true);
            if (is_string($value) && $value !== '') $operatingSystem[$key] = $value;
        }

        return new Process(array_merge([config('facenet.python')], $arguments), base_path('scripts'), array_merge($operatingSystem, [
            'PYTHONPATH' => implode(PATH_SEPARATOR, $pythonPath),
            'PYTHONIOENCODING' => 'utf-8',
            'FACENET_MODEL_PATH' => config('facenet.model_path'),
            'DB_HOST' => (string) ($database['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($database['port'] ?? 3306),
            'DB_DATABASE' => (string) ($database['database'] ?? ''),
            'DB_USERNAME' => (string) ($database['username'] ?? ''),
            'DB_PASSWORD' => (string) ($database['password'] ?? ''),
        ]), null, config('facenet.timeout'));
    }
}
