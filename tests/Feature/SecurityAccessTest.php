<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacyAuthMiddleware;
use App\Http\Middleware\ProtectSensitiveLegacyWrites;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SecurityAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'security_test',
            'database.connections.security_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        DB::statement('CREATE TABLE settings (id INTEGER PRIMARY KEY, setting_key TEXT, setting_value TEXT, description TEXT)');
        DB::table('settings')->insert([
            ['setting_key' => 'face_recognition_threshold', 'setting_value' => '0.58'],
            ['setting_key' => 'smtp_password', 'setting_value' => 'fixture-secret'],
            ['setting_key' => 'future_secret', 'setting_value' => 'fixture-secret'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('security_test');
        parent::tearDown();
    }

    public function test_employee_api_settings_exclude_secrets_and_unreviewed_keys(): void
    {
        $this->actingAs(new User(['role' => 'pegawai']))
            ->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.face_recognition_threshold', '0.58')
            ->assertJsonMissingPath('data.smtp_password')
            ->assertJsonMissingPath('data.future_secret');
    }

    public function test_admin_can_read_settings_for_the_existing_editor(): void
    {
        $this->actingAs(new User(['role' => 'admin']))
            ->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.smtp_password', 'fixture-secret');
    }

    public function test_employee_cannot_read_secret_by_key_or_update_settings(): void
    {
        $this->actingAs(new User(['role' => 'pegawai']));
        $this->withoutMiddleware([
            ProtectSensitiveLegacyWrites::class,
            LegacyAuthMiddleware::class,
        ]);
        $this->getJson('/api/settings/smtp_password')->assertForbidden();
        $this->putJson('/api/settings', ['smtp_password' => 'changed'])->assertForbidden();
        $this->assertSame('fixture-secret', DB::table('settings')->where('setting_key', 'smtp_password')->value('setting_value'));
    }

    public function test_legacy_settings_filter_guests_and_employees_but_keep_admin_editor(): void
    {
        foreach (['guest', 'pegawai', 'admin'] as $role) {
            $result = $this->legacy($role, 'get_settings');
            $this->assertSame(200, $result['status']);
            $this->assertSame('0.58', $result['body']['data']['face_recognition_threshold']['value']);
            $this->assertSame($role === 'admin', isset($result['body']['data']['smtp_password']));
            $this->assertSame($role === 'admin', isset($result['body']['data']['future_secret']));
        }
    }

    public function test_legacy_embedding_writes_require_admin_and_post(): void
    {
        foreach (['guest' => 401, 'pegawai' => 403] as $role => $status) {
            $result = $this->legacy($role, 'save_face_embedding', 'POST');
            $this->assertSame($status, $result['status']);
            $this->assertSame('original', $result['embedding']);
        }
        $result = $this->legacy('admin', 'save_face_embedding', 'GET');
        $this->assertSame(405, $result['status']);
        $this->assertSame('original', $result['embedding']);
        $result = $this->legacy('admin', 'save_face_embedding', 'POST');
        $this->assertSame(200, $result['status']);
        $this->assertCount(128, json_decode($result['embedding'], true));
    }

    public function test_user_serialization_does_not_expose_reset_or_biometric_secrets(): void
    {
        $user = new User;
        $user->forceFill([
            'nama' => 'Test', 'password_reset_token' => 'secret', 'password_reset_expires' => '2026-09-10',
            'face_embedding_128' => '[0.1]', 'advanced_features' => '{}',
            'facial_geometry' => '{}', 'feature_vector' => '[]',
        ]);
        $this->assertSame(['nama' => 'Test'], $user->toArray());
    }

    private function legacy(string $role, string $action, string $method = 'GET'): array
    {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/legacy_security.php'), $role, $action, $method]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
