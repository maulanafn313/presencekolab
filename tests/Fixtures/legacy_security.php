<?php

// Isolated handler fixture: no core bootstrap, MySQL, real sessions or user data.
require dirname(__DIR__, 2).'/vendor/autoload.php';

$_SESSION = $argv[1] === 'guest' ? [] : ['user' => ['id' => 1, 'role' => $argv[1]]];
$_REQUEST = ['ajax' => $argv[2]];
$_GET = [];
$_SERVER['REQUEST_METHOD'] = $argv[3] ?? 'GET';
$_POST = ['id' => 1, 'embedding' => json_encode(array_fill(0, 128, 0.1)), 'landmarks' => null];
if (($argv[4] ?? '') === 'invalid') {
    $_POST['embedding'] = '[0.1]';
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE settings (setting_key TEXT, setting_value TEXT, description TEXT)');
$pdo->exec("INSERT INTO settings VALUES ('face_recognition_threshold','0.58','public'), ('smtp_password','fixture-secret','private'), ('future_secret','fixture-secret','private')");
$pdo->exec('CREATE TABLE users (id INTEGER, face_embedding_128 TEXT, face_landmarks TEXT)');
$pdo->exec("INSERT INTO users VALUES (1, 'original', NULL)");

function isAdmin(): bool
{
    return ($_SESSION['user']['role'] ?? null) === 'admin';
}

function jsonResponse($data, $status = 200): never
{
    global $pdo;
    echo json_encode([
        'status' => $status,
        'body' => $data,
        'embedding' => $pdo->query('SELECT face_embedding_128 FROM users WHERE id=1')->fetchColumn(),
    ], JSON_THROW_ON_ERROR);
    exit;
}

require dirname(__DIR__, 2).'/app/Legacy/ajax_handler.php';
