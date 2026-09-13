<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Middleware\SafeResponses;
use App\Models\User;
use App\Services\ApiListing;
use App\Services\Attendance\AdminAttendance;
use App\Services\Attendance\AttendanceAudit;
use App\Services\Attendance\AttendanceCorrection;
use App\Services\Attendance\AttendanceSubmission;
use App\Services\DatabaseBackup;
use App\Services\SessionLifecycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RoadmapIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'roadmap_test', 'database.connections.roadmap_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'backup.auto_enabled' => false]);
        $_SESSION = [];
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'roadmap_test', '--force' => true]), Artisan::output());
        DB::table('users')->insert(['id' => 1, 'role' => 'pegawai', 'nim' => 'TEST-1', 'nama' => 'Fixture', 'email' => 'fixture@example.test', 'password' => 'test-hash']);
        DB::table('settings')->insert([
            ['setting_key' => 'wfo_mode', 'setting_value' => 'coordinate'],
            ['setting_key' => 'wfo_lat', 'setting_value' => '-6.975'],
            ['setting_key' => 'wfo_lng', 'setting_value' => '107.630'],
            ['setting_key' => 'wfo_radius', 'setting_value' => '200'],
        ]);
        Carbon::setTestNow(Carbon::parse('2026-09-14 07:30:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $_SESSION = [];
        DB::purge('roadmap_test');
        parent::tearDown();
    }

    private function input(array $extra = []): array
    {
        return array_replace(['nim' => 'TEST-1', 'mode' => 'masuk', 'lat' => '-6.975', 'lng' => '107.630', 'lokasi' => 'Kantor', 'gps_accuracy' => 10], $extra);
    }

    public function test_legacy_attendance_dispatch_reaches_submission_before_unknown_action_fallback(): void
    {
        require_once app_path('Legacy/core.php');
        $_SESSION['user'] = ['id'=>1, 'role'=>'admin', 'nim'=>'TEST-1'];
        $request = Request::create('/presensi-masuk?ajax=save_attendance', 'POST', $this->input());
        $response = app(\App\Services\LegacyPageRenderer::class)->render($request, 'presensi-masuk');
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertTrue($response->getData(true)['ok']);
        $this->assertSame(1, DB::table('attendance')->count());
        $unknown = app(\App\Services\LegacyPageRenderer::class)->render(Request::create('/?ajax=unknown_test'), 'landing');
        $this->assertSame(404, $unknown->getStatusCode());
    }

    public function test_qa_registration_converts_base64_to_file_and_saves_photo_with_embedding(): void
    {
        $_SESSION['user'] = ['id'=>1, 'role'=>'admin', 'nim'=>'TEST-1'];
        $this->mock(\App\Services\FaceNetProcess::class, function ($mock) {
            $mock->shouldReceive('make')->once()->andReturnUsing(function ($json) {
                $args = json_decode($json, true);
                $this->assertSame('generate_embedding', $args['action']);
                $this->assertFileExists($args['image']);
                $this->assertStringNotContainsString('base64,', $args['image']);
                return new \Symfony\Component\Process\Process([PHP_BINARY, '-r', 'echo json_encode(["success"=>true,"data"=>["embedding"=>array_fill(0,512,0.125)]]);']);
            });
        });
        $pixels = imagecreatetruecolor(16,16);
        ob_start(); imagejpeg($pixels); $photo = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
        imagedestroy($pixels);
        $response = app(\App\Services\LegacyPageRenderer::class)->render(Request::create('/?ajax=generate_face_embedding', 'POST', ['image'=>$photo]));
        $name = DB::table('users')->value('foto_base64');
        try {
            $this->assertSame(200, $response->getStatusCode(), $response->getContent());
            $this->assertSame('ready', $response->getData(true)['face_status']);
            $this->assertCount(512, json_decode(DB::table('users')->value('face_embedding'), true));
            $this->assertNotEmpty($name);
        } finally {
            if ($name && ($path = \App\Services\PrivateMedia::path($name))) unlink($path);
        }
    }

    public function test_qa_rejects_invalid_photo_without_overwriting_existing_registration(): void
    {
        $_SESSION['user'] = ['id'=>1, 'role'=>'admin', 'nim'=>'TEST-1'];
        DB::table('users')->where('id',1)->update(['foto_base64'=>'previous.jpg']);
        $response = app(\App\Services\LegacyPageRenderer::class)->render(Request::create('/?ajax=generate_face_embedding', 'POST', ['image'=>'not-an-image']));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['ok']);
        $this->assertSame('previous.jpg', DB::table('users')->value('foto_base64'));
    }

    public function test_qa_python_failure_preserves_existing_photo_and_returns_failure(): void
    {
        $_SESSION['user'] = ['id'=>1, 'role'=>'admin', 'nim'=>'TEST-1'];
        DB::table('users')->where('id',1)->update(['foto_base64'=>'previous.jpg', 'face_embedding'=>'[0.1]']);
        $this->mock(\App\Services\FaceNetProcess::class, fn ($mock) => $mock->shouldReceive('make')->once()->andThrow(new \RuntimeException('Fixture unavailable')));
        $pixels = imagecreatetruecolor(16,16);
        ob_start(); imagejpeg($pixels); $photo = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
        imagedestroy($pixels);
        $response = app(\App\Services\LegacyPageRenderer::class)->render(Request::create('/?ajax=generate_face_embedding', 'POST', ['image'=>$photo]));
        $this->assertSame(503, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['ok']);
        $this->assertSame('previous.jpg', DB::table('users')->value('foto_base64'));
        $this->assertSame('[0.1]', DB::table('users')->value('face_embedding'));
    }

    public function test_evidence_endpoint_allows_owner_and_denies_other_user(): void
    {
        $this->assertTrue($this->submit()['ok']);
        $id = DB::table('attendance')->value('id');
        DB::table('attendance')->where('id', $id)->update(['foto_masuk'=>'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1cAAAAASUVORK5CYII=']);
        $request = Request::create('/api/attendance/'.$id.'/evidence/masuk');
        $request->setUserResolver(fn () => User::findOrFail(1));
        $controller = app(\App\Http\Controllers\Api\AttendanceEvidenceController::class);
        $this->assertSame('image/png', $controller->show($request, $id, 'masuk')->headers->get('Content-Type'));
        $other = new User(['role'=>'pegawai']); $other->id = 99;
        $request->setUserResolver(fn () => $other);
        try {
            $controller->show($request, $id, 'masuk');
            $this->fail('Other user accessed evidence');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_admin_creation_uses_selected_date_and_records_audit(): void
    {
        $row = app(AdminAttendance::class)->create([
            'user_id' => 1, 'tanggal' => '2026-08-17', 'ket' => 'wfo', 'status' => 'ontime', 'jam_masuk' => '08:15',
        ], 1);
        $this->assertSame('2026-08-17', substr((string) $row->jam_masuk_iso, 0, 10));
        $this->assertSame(1, DB::table('attendance_corrections')->count());
    }

    public function test_admin_creation_rejects_invalid_time_without_partial_write(): void
    {
        try {
            app(AdminAttendance::class)->create([
                'user_id' => 1, 'tanggal' => '2026-08-17', 'ket' => 'wfo', 'status' => 'ontime',
                'jam_masuk' => '09:00', 'jam_pulang' => '08:00',
            ], 1);
            $this->fail('Invalid order accepted');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('attendance')->count());
            $this->assertSame(0, DB::table('attendance_corrections')->count());
        }
    }

    public function test_safe_response_removes_nested_diagnostics_and_adds_request_id(): void
    {
        $middleware = app(SafeResponses::class);
        $response = $middleware->handle(Request::create('/api/probe'), fn () => response()->json([
            'ok' => false, 'debug_error' => 'secret/path', 'data' => ['trace' => 'secret/path', 'name' => 'safe'],
        ]));
        $this->assertStringNotContainsString('secret/path', $response->getContent());
        $this->assertNotEmpty($response->headers->get('X-Request-ID'));
        $failed = $middleware->handle(Request::create('/api/probe'), fn () => response()->json(['message' => 'secret/path'], 500));
        $this->assertStringNotContainsString('secret/path', $failed->getContent());
    }

    public function test_paginated_listing_preserves_array_contract_and_counts(): void
    {
        $result = ApiListing::get(User::query(), Request::create('/api/users?per_page=1'));
        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['meta']['total']);
        $legacy = ApiListing::get(User::query(), Request::create('/api/users'));
        $this->assertArrayHasKey('meta', $legacy);
    }

    private function submit(array $extra = []): array
    {
        return app(AttendanceSubmission::class)->submit($this->input($extra), ['id' => 1, 'role' => 'pegawai', 'nim' => 'TEST-1'])->getData(true);
    }

    public function test_clean_install_contains_legacy_tables_and_unique_day_guard(): void
    {
        foreach (['intern_groups', 'intern_group_members', 'kpi_monthly_cache', 'attendance_submissions', 'attendance_corrections'] as $table) {
            $this->assertTrue(DB::getSchemaBuilder()->hasTable($table));
        }
        DB::table('attendance')->insert(['user_id' => 1, 'jam_masuk_iso' => '2026-09-14 07:00:00']);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('attendance')->insert(['user_id' => 1, 'jam_masuk_iso' => '2026-09-14 08:00:00']);
    }

    public function test_workday_wfo_and_retry_store_exactly_one_record(): void
    {
        $first = $this->submit(['request_id' => 'same-action']);
        $this->assertTrue($first['ok'], json_encode($first));
        $this->assertSame($first, $this->submit(['request_id' => 'same-action']));
        $this->assertSame(1, DB::table('attendance')->count());
        $this->assertSame('ontime', DB::table('attendance')->value('status'));
        $this->assertFalse($this->submit()['ok']);
    }

    public function test_late_arrival_and_early_leave_keep_existing_approval_rules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:15:00', 'Asia/Jakarta'));
        $this->assertTrue($this->submit()['ok']);
        $this->assertSame('terlambat', DB::table('attendance')->value('status'));
        Carbon::setTestNow(Carbon::parse('2026-09-14 15:00:00', 'Asia/Jakarta'));
        $this->assertTrue($this->submit(['mode' => 'pulang'])['need_early_leave_reason']);
        $this->assertTrue($this->submit(['mode' => 'pulang', 'early_leave_reason' => 'Keperluan keluarga'])['ok']);
        $this->assertNull(DB::table('attendance')->value('jam_pulang_iso'));
        $this->assertSame('pending', DB::table('admin_help_requests')->value('status'));
    }

    public function test_wfa_weekend_and_holiday_still_require_approval(): void
    {
        $wfa = $this->submit(['lat' => '-6.2', 'lng' => '106.8']);
        $this->assertTrue($wfa['need_reason']);
        $this->assertTrue($this->submit(['lat' => '-6.2', 'lng' => '106.8', 'wfa_reason' => 'Tugas luar'])['ok']);
        $this->assertSame(0, DB::table('attendance')->count());
        $this->assertSame('wfa', DB::table('admin_help_requests')->value('attendance_type'));
        Carbon::setTestNow(Carbon::parse('2026-09-19 09:00:00', 'Asia/Jakarta'));
        $this->assertTrue($this->submit()['need_overtime_reason']);
        $this->assertTrue($this->submit(['overtime_reason' => 'Rilis proyek'])['ok']);
        $this->assertSame(2, DB::table('admin_help_requests')->count());
    }

    public function test_normal_checkout_and_api_adapter_share_business_decisions(): void
    {
        $user = User::findOrFail(1);
        $request = Request::create('/api/attendance/clock-in', 'POST', [
            'lat_masuk' => '-6.975', 'lng_masuk' => '107.630', 'lokasi_masuk' => 'Kantor', 'ket' => 'wfo', 'gps_accuracy' => 10,
        ]);
        $request->setUserResolver(fn () => $user);
        $response = app(AttendanceController::class)->clockIn($request);
        $this->assertSame(201, $response->getStatusCode(), $response->getContent());
        Carbon::setTestNow(Carbon::parse('2026-09-14 17:01:00', 'Asia/Jakarta'));
        $this->assertTrue($this->submit(['mode' => 'pulang'])['ok']);
        $this->assertNotNull(DB::table('attendance')->value('jam_pulang_iso'));
    }

    public function test_correction_is_audited_and_invalid_order_is_rejected_without_writing(): void
    {
        $this->assertTrue($this->submit()['ok']);
        $id = DB::table('attendance')->value('id');
        $service = app(AttendanceCorrection::class);
        $service->update($id, ['jam_pulang_iso' => '2026-09-14 17:00:00'], 1, 'Dokumen pendukung terverifikasi');
        $this->assertSame(1, DB::table('attendance_corrections')->count());
        try {
            $service->update($id, ['jam_pulang_iso' => '2026-09-13 17:00:00'], 1, 'Invalid');
            $this->fail('Invalid time accepted');
        } catch (ValidationException) {
            $this->assertSame(1, DB::table('attendance_corrections')->count());
            $this->assertSame(0, app(AttendanceAudit::class)->summary()['invalid_time_order']);
        }
    }

    public function test_session_logout_works_without_a_personal_access_token(): void
    {
        $request = Request::create('/logout');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $user = User::findOrFail(1);
        $request->setUserResolver(fn () => $user);
        app(SessionLifecycle::class)->login($request, $user);
        $this->assertSame(1, $session->get('user.id'));
        app(SessionLifecycle::class)->logout($request);
        $this->assertFalse($session->has('user'));
        $this->assertEmpty($_SESSION);
    }

    public function test_backup_round_trip_restores_synthetic_data_without_removing_previous_backup(): void
    {
        $directory = storage_path('framework/testing/backup-'.bin2hex(random_bytes(6)));
        try {
            $backup = app(DatabaseBackup::class);
            $first = $backup->create(DB::connection(), $directory);
            $second = $backup->create(DB::connection(), $directory);
            $this->assertFileExists($first['path']);
            $this->assertSame($second['sha256'], hash_file('sha256', $second['path']));
            $restored = new \PDO('sqlite::memory:');
            $restored->exec(file_get_contents($second['path']));
            $this->assertSame('TEST-1', $restored->query('SELECT nim FROM users')->fetchColumn());
        } finally {
            File::deleteDirectory($directory); // Isolated generated test directory only.
        }
    }
}
