<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Never migrate or seed the application's MySQL database for these tests.
        config([
            'database.default' => 'auth_test',
            'database.connections.auth_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_04_16_030010_create_personal_access_tokens_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    protected function tearDown(): void
    {
        DB::purge('auth_test');
        parent::tearDown();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $this->postJson('/api/login', ['email' => 'invalid', 'password' => 'test-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unknown_account_does_not_receive_a_token(): void
    {
        $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => 'test-password'])
            ->assertUnauthorized()
            ->assertJsonPath('ok', false)
            ->assertJsonMissingPath('token');

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_wrong_password_does_not_receive_a_token(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertJsonPath('ok', false)
            ->assertJsonMissingPath('token');

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_valid_login_returns_json_and_a_persisted_token(): void
    {
        $user = $this->createUser();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'test-password',
        ])->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('ok', true)
            ->assertJsonPath('role', 'pegawai')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertNotNull($token);
        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertSame(1, PersonalAccessToken::count());
    }

    private function createUser(): User
    {
        return User::create([
            'nama' => 'Pengguna Uji',
            'email' => 'auth-test@example.test',
            'role' => 'pegawai',
            'password' => 'test-password',
        ]);
    }
}
