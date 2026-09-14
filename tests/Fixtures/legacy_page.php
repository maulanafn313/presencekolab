<?php

use App\Services\LegacyPageRenderer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Exercise the real renderer in a separate process: legacy JSON responses exit.
// All database and session state is synthetic; never connect to the user's DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'legacy_page_test',
    'database.connections.legacy_page_test' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
    ],
    'session.driver' => 'array',
    'cache.default' => 'array',
]);

$connection = DB::connection();
$connection->statement('CREATE TABLE settings (setting_key TEXT, setting_value TEXT, description TEXT)');
$connection->table('settings')->insert([
    ['setting_key' => 'face_recognition_threshold', 'setting_value' => '0.58', 'description' => 'public'],
    ['setting_key' => 'smtp_password', 'setting_value' => 'fixture-only-secret', 'description' => 'private'],
]);
$connection->statement('CREATE TABLE users (id INTEGER, role TEXT, nim TEXT, nama TEXT, email TEXT, password TEXT, prodi TEXT, startup TEXT, foto_base64 TEXT, face_embedding_128 TEXT)');
$connection->statement('CREATE TABLE intern_group_members (id INTEGER, group_id INTEGER, user_id INTEGER)');
$connection->statement('CREATE TABLE intern_groups (id INTEGER, is_archived INTEGER)');

$_SESSION = [];
$role = $argv[2] ?? 'guest';
if ($role !== 'guest') {
    $user = ['id' => 1, 'role' => $role, 'nim' => 'TEST-1', 'nama' => 'Fixture User', 'email' => 'fixture@example.test'];
    $connection->table('users')->insert($user + ['password' => 'fixture-password-hash']);
    $app['session']->driver()->put([
        'user' => $user,
        'auth_password_fingerprint' => hash('sha256', 'fixture-password-hash'),
    ]);
}

register_shutdown_function(static function (): void {
    fwrite(STDERR, 'FIXTURE_STATUS='.(http_response_code() ?: 200));
});

$request = Request::create($argv[1] ?? '/');
$mode = $argv[3] ?? 'http';
if ($mode === 'http') {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    http_response_code($response->getStatusCode());
    echo $response->getContent();
} else {
    // Both successful rendering and exceptions must leave caller state intact.
    $_GET = ['sentinel' => 'query'];
    $_POST = ['sentinel' => 'body'];
    $_REQUEST = ['sentinel' => 'request'];
    $GLOBALS['pdo'] = 'caller-connection-sentinel';
    $level = ob_get_level();
    $renderer = $app->make(LegacyPageRenderer::class);
    if ($mode === 'exception') {
        (new ReflectionProperty($renderer, 'pagesPath'))->setValue($renderer, __DIR__.'/nonexistent-views');
    }
    $caught = false;
    try {
        $renderer->render($request, 'login');
    } catch (Throwable $e) {
        $caught = true;
    }
    echo json_encode([
        'caught' => $caught,
        'buffers_restored' => $level === ob_get_level(),
        'get' => $_GET, 'post' => $_POST, 'request' => $_REQUEST,
        'pdo_restored' => $GLOBALS['pdo'] === 'caller-connection-sentinel',
    ], JSON_THROW_ON_ERROR);
}
