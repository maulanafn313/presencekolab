<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class LegacyPageRenderingTest extends TestCase
{
    public function test_settings_reach_the_handler_with_a_database_connection_and_no_layout(): void
    {
        foreach (['/?ajax=get_settings', '/index.php?ajax=get_settings', '/ajax_handler.php?ajax=get_settings'] as $url) {
            $output = $this->request($url);
            $body = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('0.58', $body['data']['face_recognition_threshold']['value']);
            $this->assertArrayNotHasKey('smtp_password', $body['data']);
            $this->assertStringNotContainsString('<html', $output);
        }
    }

    public function test_protected_legacy_read_returns_unauthorized_instead_of_database_error(): void
    {
        $body = json_decode($this->request('/?ajax=get_members', 'guest', 401), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($body['ok']);
    }

    public function test_public_pages_and_both_dashboard_roles_render(): void
    {
        foreach (['/', '/login', '/register', '/presensi-masuk', '/presensi-pulang'] as $url) {
            $output = $this->request($url);
            $this->assertStringContainsString('</html>', $output);
            $this->assertStringNotContainsString('Undefined variable', $output);
            $this->assertStringNotContainsString('@if (', $output);
            $this->assertStringNotContainsString('@elseif (', $output);
        }
        $this->assertStringContainsString('SPBW Admin', $this->request('/dashboard', 'admin'));
        $this->assertStringContainsString('robot-cat-character', $this->request('/dashboard', 'pegawai'));
        $this->request('/dashboard', 'guest', 302);
    }

    public function test_page_array_is_a_validation_error_instead_of_a_type_error(): void
    {
        $this->getJson('/?page[]=login')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_renderer_restores_buffers_and_caller_state_even_when_a_template_fails(): void
    {
        foreach (['context', 'exception'] as $mode) {
            $state = json_decode($this->request('/login', 'guest', 200, $mode), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($mode === 'exception', $state['caught']);
            $this->assertTrue($state['buffers_restored']);
            $this->assertTrue($state['pdo_restored']);
            $this->assertSame(['sentinel' => 'query'], $state['get']);
            $this->assertSame(['sentinel' => 'body'], $state['post']);
            $this->assertSame(['sentinel' => 'request'], $state['request']);
        }
    }

    private function request(string $url, string $role = 'guest', int $status = 200, string $mode = 'http'): string
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/legacy_page.php'), $url, $role, $mode], base_path());
        $process->setTimeout(30);
        $process->mustRun();
        $this->assertStringContainsString('FIXTURE_STATUS='.$status, $process->getErrorOutput(), $process->getOutput());

        return $process->getOutput();
    }
}
