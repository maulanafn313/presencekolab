<?php

// Dedicated loopback test router, never a production route or a real database.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) {
    http_response_code(404); exit;
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config([
    'database.default'=>'facenet_http_fixture',
    'database.connections.facenet_http_fixture'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''],
    'session.driver'=>'array', 'cache.default'=>'array', 'backup.auto_enabled'=>false,
]);
\Illuminate\Support\Facades\Artisan::call('migrate', ['--database'=>'facenet_http_fixture','--force'=>true]);
$user = ['id'=>1,'role'=>'admin','nim'=>'HTTP-FIXTURE','nama'=>'HTTP Fixture','email'=>'http-fixture@example.test'];
\Illuminate\Support\Facades\DB::table('users')->insert($user + ['password'=>'fixture-hash']);
$app['session']->driver()->put(['user'=>$user, 'auth_password_fingerprint'=>hash('sha256','fixture-hash'), '_token'=>'fixture-csrf']);
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$row = \App\Models\User::find(1);
$name = $row?->getRawOriginal('foto_base64');
if ($response instanceof \Illuminate\Http\JsonResponse && $response->getStatusCode() === 200) {
    $body = $response->getData(true);
    $body['verification'] = ['photo_saved'=>(bool)$name, 'embedding_dimensions'=>count(json_decode($row->getRawOriginal('face_embedding') ?? '[]', true))];
    $response->setData($body);
}
$response->send();
if ($name && ($path = \App\Services\PrivateMedia::path($name))) unlink($path); // Only generated fixture photo.
$kernel->terminate($request, $response);
