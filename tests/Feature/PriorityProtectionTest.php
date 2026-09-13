<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacyAuthMiddleware;
use App\Http\Middleware\ProtectSensitiveLegacyWrites;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PriorityProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'protection_test',
            'database.connections.protection_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
        ]);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        // Keep the catch-all legacy page route from shadowing this test-only endpoint.
        foreach (Route::getRoutes() as $route) {
            if ($route->uri() === '{page}') {
                $route->where('page', '(?!protection-probe$).*');
            }
        }
        // Exercise the real web middleware without executing legacy import/write logic.
        Route::any('/protection-probe', fn () => response()->json(['reached' => true]))->middleware('web');
    }

    protected function tearDown(): void
    {
        DB::purge('protection_test');
        parent::tearDown();
    }

    public function test_sensitive_actions_require_csrf_before_the_handler(): void
    {
        foreach (['save_setting', 'update_settings', 'save_face_embedding', 'import_db'] as $action) {
            $this->postJson('/protection-probe?ajax='.$action)->assertStatus(419)->assertJsonMissingPath('reached');
            $this->postJson('/protection-probe', ['ajax' => $action])->assertStatus(419);
            $this->postJson('/protection-probe', ['action' => $action])->assertStatus(419);
            $this->getJson('/protection-probe?ajax='.$action)->assertStatus(405);
        }
    }

    public function test_csrf_accepts_matching_header_and_form_token_and_rejects_mismatch(): void
    {
        $this->withSession(['_token' => 'expected-token']);
        $this->postJson('/protection-probe?ajax=update_settings', [], ['X-CSRF-TOKEN' => 'wrong-token'])
            ->assertStatus(419);
        $this->postJson('/protection-probe?ajax=update_settings', [], ['X-CSRF-TOKEN' => 'expected-token'])
            ->assertOk()->assertJsonPath('reached', true);
        $this->postJson('/protection-probe', ['ajax' => 'save_setting', '_token' => 'expected-token'])
            ->assertOk()->assertJsonPath('reached', true);
    }

    public function test_conflicting_query_and_body_cannot_bypass_csrf(): void
    {
        $this->postJson('/protection-probe?ajax=import_db', ['ajax' => 'get_settings'])->assertStatus(400);
        $this->postJson('/protection-probe?ajax=get_settings', ['ajax' => 'import_db'])->assertStatus(400);
    }

    public function test_public_settings_read_is_still_available(): void
    {
        $this->getJson('/protection-probe?ajax=get_settings')->assertOk();
        $this->withSession(['_token' => 't'])->postJson('/protection-probe?ajax=get_settings', ['_token' => 't'])->assertOk();
    }

    public function test_employee_cannot_list_change_create_or_delete_users(): void
    {
        $employee = $this->employee('one@example.test');
        $other = $this->employee('two@example.test');
        $this->actingAs($employee);

        $this->withoutMiddleware([
            ProtectSensitiveLegacyWrites::class,
            LegacyAuthMiddleware::class,
        ]);
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/users/'.$other->id)->assertForbidden();
        $this->getJson('/api/users/'.$other->id.'/photo')->assertForbidden();
        $this->postJson('/api/users', ['email' => 'new@example.test'])->assertForbidden();
        $this->putJson('/api/users/'.$other->id, ['nama' => 'changed'])->assertForbidden();
        $this->patchJson('/api/users/'.$employee->id, ['role' => 'admin'])->assertForbidden();
        $this->deleteJson('/api/users/'.$other->id)->assertForbidden();
        $this->assertSame(2, User::count());
        $this->assertSame('Pegawai Uji', $other->fresh()->nama);
        $this->assertSame('pegawai', $employee->fresh()->role);
    }

    public function test_employee_can_read_own_profile_and_admin_can_list_users(): void
    {
        $employee = $this->employee('one@example.test');
        $this->actingAs($employee)->getJson('/api/users/'.$employee->id)->assertOk();
        $admin = new User(['role' => 'admin']);
        $admin->id = 99;
        $this->actingAs($admin)->getJson('/api/users')->assertOk();
    }

    private function employee(string $email): User
    {
        return User::create(['email' => $email, 'nama' => 'Pegawai Uji', 'role' => 'pegawai', 'password' => 'test-password']);
    }
}
