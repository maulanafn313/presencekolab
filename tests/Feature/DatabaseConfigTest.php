<?php

namespace Tests\Feature;

use ErrorException;
use Tests\TestCase;

class DatabaseConfigTest extends TestCase
{
    public function test_database_config_loads_without_warnings_or_output(): void
    {
        // Catch warnings at the source, before Laravel can render them into HTTP output.
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        ob_start();

        try {
            $config = require config_path('database.php');
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        $this->assertSame('', $output);
        $this->assertSame('mysql', $config['connections']['mysql']['driver']);
        $this->assertSame('mariadb', $config['connections']['mariadb']['driver']);
    }
}
