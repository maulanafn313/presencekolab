<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleSensitiveRequests;
use Illuminate\Http\Request;
use Tests\TestCase;

class SensitiveRateLimitTest extends TestCase
{
    public function test_api_and_legacy_share_login_limit_and_normalize_email(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame(200, $this->send('/api/login', ['email' => ' USER@example.test '])->getStatusCode());
        }
        $response = $this->send('/?ajax=login', ['email' => 'user@example.test']);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertFalse(json_decode($response->getContent(), true)['ok']);
        $this->assertSame(200, $this->send('/?ajax=login', ['email' => 'other@example.test'])->getStatusCode());
        $this->travel(61)->seconds();
        $this->assertSame(200, $this->send('/api/login', ['email' => 'user@example.test'])->getStatusCode());
    }

    public function test_ip_limit_cannot_be_bypassed_by_rotating_email(): void
    {
        config(['security.request_limits.login.per_ip' => 2]);
        $this->send('/api/login', ['email' => 'a@example.test']);
        $this->send('/?ajax=login', ['email' => 'b@example.test']);
        $this->assertSame(429, $this->send('/api/login', ['email' => 'c@example.test'])->getStatusCode());
    }

    public function test_recovery_actions_share_a_limit_and_face_apis_share_a_limit(): void
    {
        config(['security.request_limits.recovery.per_account_ip' => 2, 'security.request_limits.face.per_ip' => 1]);
        $this->assertSame(200, $this->send('/?ajax=forgot_password', ['email' => 'a@example.test'])->getStatusCode());
        $this->assertSame(200, $this->send('/', ['action' => 'verify_otp', 'email' => 'a@example.test'])->getStatusCode());
        $this->assertSame(429, $this->send('/', ['ajax' => 'reset_password', 'email' => 'a@example.test'])->getStatusCode());
        $this->assertSame(200, $this->send('/api/face/identify')->getStatusCode());
        $this->assertSame(429, $this->send('/api/face/verify')->getStatusCode());
    }

    public function test_real_api_route_is_throttled_before_login_controller(): void
    {
        config(['security.request_limits.login.per_ip' => 1]);
        $this->postJson('/api/login', [])->assertUnprocessable();
        $this->postJson('/api/login', [])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_settings_reads_are_not_throttled_and_auth_get_is_rejected(): void
    {
        $this->assertSame(200, $this->send('/?ajax=get_settings', [], 'GET')->getStatusCode());
        $this->assertSame(405, $this->send('/?ajax=login', [], 'GET')->getStatusCode());
    }

    private function send(string $url, array $data = [], string $method = 'POST')
    {
        $request = Request::create($url, $method, $data, [], [], ['REMOTE_ADDR' => '192.0.2.1']);

        return app(ThrottleSensitiveRequests::class)->handle($request, fn () => response()->json(['reached' => true]));
    }
}
