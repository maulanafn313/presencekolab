<?php

use App\Services\FaceEmbeddingInput;

// Extracted from ajax_handler.php — action group: face
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'save_face_embedding') {
    if (! isset($_SESSION['user']['id'])) {
        jsonResponse(['ok' => false, 'message' => 'Unauthorized'], 401);
    }
    if (! isAdmin()) {
        jsonResponse(['ok' => false, 'message' => 'Hanya admin yang dapat memperbarui data wajah.'], 403);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['ok' => false, 'message' => 'Method not allowed'], 405);
    }
    $id = (int) ($_POST['id'] ?? 0);
    $embedding = $_POST['embedding'] ?? null;
    $landmarks = $_POST['landmarks'] ?? null;

    try {
        FaceEmbeddingInput::validate($embedding, $landmarks);
    } catch (InvalidArgumentException $e) {
        jsonResponse(['ok' => false, 'message' => $e->getMessage()], 422);
    }

    if ($id > 0 && $embedding) {
        // Save specifically to face_embedding_128 for frontend use
        $stmt = $pdo->prepare('UPDATE users SET face_embedding_128 = :embedding, face_landmarks = :landmarks WHERE id = :id');
        $res = $stmt->execute([
            ':embedding' => $embedding,
            ':landmarks' => $landmarks,
            ':id' => $id,
        ]);
        jsonResponse(['ok' => $res, 'message' => 'Frontend 128-dim embedding updated.']);
    }
    jsonResponse(['ok' => false, 'message' => 'Invalid data'], 400);
}

if ($action === 'generate_face_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = \App\Models\User::find($_SESSION['user']['id'] ?? 0);
    if (!$user) jsonResponse(['ok'=>false, 'message'=>'Silakan login terlebih dahulu.'], 401);
    $image = $_POST['image'] ?? null;
    if (!is_string($image) || $image === '') jsonResponse(['ok'=>false, 'message'=>'Foto wajib diisi.'], 422);
    try {
        $ready = app(\App\Services\FaceRegistration::class)->registerImage($user, $image);
        if (!$ready) jsonResponse(['ok'=>false, 'face_status'=>'failed', 'message'=>'Wajah tidak terdeteksi. Gunakan foto yang jelas dan coba lagi.'], 422);
        jsonResponse(['ok'=>true, 'face_status'=>'ready', 'message'=>'Foto dan data wajah berhasil disimpan.']);
    } catch (\Illuminate\Validation\ValidationException $e) {
        jsonResponse(['ok'=>false, 'message'=>'Foto tidak valid.', 'errors'=>$e->errors()], 422);
    } catch (\Exception $e) {
        report($e);
        jsonResponse(['ok'=>false, 'face_status'=>'failed', 'message'=>'Layanan wajah belum tersedia. Silakan coba lagi atau periksa konfigurasi FaceNet.'], 503);
    }
}

// New Endpoint: Save frontend-computed embedding to database

if ($action === 'save_computed_face_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? null;
    $embedding = $_POST['embedding'] ?? null;

    if ($userId && $embedding) {
        $stmt = $pdo->prepare('UPDATE users SET face_embedding = ?, face_embedding_updated = NOW() WHERE id = ?');
        if ($stmt->execute([$embedding, $userId])) {
            jsonResponse(['ok' => true]);
        }
    }
    jsonResponse(['error' => 'Invalid data'], 400);
}

if ($action === 'recognize_face' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    // Use the new recognize_face endpoint
    $data = [
        'action' => 'recognize_face',
        'image' => $base64Image,
        'threshold' => 1.0,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'data' => $result['data']]);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Face recognition failed'], 500);
        }
    } else {
        jsonResponse(['error' => 'Face recognition failed'], 500);
    }
}

if ($action === 'process_attendance_facenet' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    // Use the new process_attendance endpoint
    $data = [
        'action' => 'process_attendance',
        'image' => $base64Image,
        'threshold' => 1.0,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'data' => $result['data']]);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Attendance processing failed'], 500);
        }
    } else {
        jsonResponse(['error' => 'Attendance processing failed'], 500);
    }
}

// Settings helpers for client usage

if ($action === 'generate_enhanced_face_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    // Use the enhanced save_embedding endpoint
    $data = [
        'action' => 'save_enhanced_embedding',
        'image' => $base64Image,
        'user_id' => $_SESSION['user']['id'],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_enhanced_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'message' => 'Enhanced face embedding generated and saved successfully']);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Failed to generate enhanced face embedding'], 500);
        }
    } else {
        jsonResponse(['error' => 'Failed to generate enhanced face embedding'], 500);
    }
}

if ($action === 'recognize_enhanced_face' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    // Use the enhanced recognize_face endpoint
    $data = [
        'action' => 'recognize_enhanced_face',
        'image' => $base64Image,
        'threshold' => 1.0,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_enhanced_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'data' => $result['data']]);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Enhanced face recognition failed'], 500);
        }
    } else {
        jsonResponse(['error' => 'Enhanced face recognition failed'], 500);
    }
}

if ($action === 'process_enhanced_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    // Use the enhanced process_attendance endpoint
    $data = [
        'action' => 'process_enhanced_attendance',
        'image' => $base64Image,
        'threshold' => 1.0,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_enhanced_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'data' => $result['data']]);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Enhanced attendance processing failed'], 500);
        }
    } else {
        jsonResponse(['error' => 'Enhanced attendance processing failed'], 500);
    }
}

// High Accuracy FaceNet Endpoints

if ($action === 'process_high_accuracy_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $userId = $_SESSION['user']['id'] ?? null;
    $result = processHighAccuracyAttendance($base64Image, $userId);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'High accuracy attendance processing failed'], 500);
    }
}

if ($action === 'generate_high_accuracy_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $userId = $_SESSION['user']['id'];
    $result = generateHighAccuracyEmbedding($base64Image, $userId);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'High accuracy embedding generation failed'], 500);
    }
}

if ($action === 'get_high_accuracy_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }

    $stats = getHighAccuracyPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get high accuracy performance stats'], 500);
    }
}

// Optimized FaceNet Endpoints - iPhone-like Performance

if ($action === 'process_optimized_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';
    $threshold = floatval($_POST['threshold'] ?? 0.5);

    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $result = processOptimizedAttendance($base64Image, $threshold);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Optimized attendance processing failed'], 500);
    }
}

if ($action === 'recognize_face_optimized' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';
    $threshold = floatval($_POST['threshold'] ?? 0.5);

    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $result = recognizeFaceOptimized($base64Image, $threshold);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Optimized face recognition failed'], 500);
    }
}

if ($action === 'generate_optimized_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $result = generateOptimizedEmbedding($base64Image);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Optimized embedding generation failed'], 500);
    }
}

if ($action === 'get_optimized_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }

    $stats = getOptimizedPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get optimized performance stats'], 500);
    }
}

// Ultra Accurate FaceNet Endpoints - Maximum Accuracy with Ultra-Fast Response

if ($action === 'process_ultra_accurate_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';
    $validationLevel = $_POST['validation_level'] ?? 'normal';

    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $result = processUltraAccurateAttendance($base64Image, $validationLevel);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Ultra accurate attendance processing failed'], 500);
    }
}

if ($action === 'get_ultra_accurate_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }

    $stats = getUltraAccuratePerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get ultra accurate performance stats'], 500);
    }
}

// iPhone-Level Accurate FaceNet Endpoints - Maximum Accuracy with Unique Feature Analysis

if ($action === 'process_iphone_level_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';

    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $result = processIPhoneLevelAttendance($base64Image);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'iPhone-level attendance processing failed'], 500);
    }
}

if ($action === 'get_iphone_level_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }

    $stats = getIPhoneLevelPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get iPhone-level performance stats'], 500);
    }
}

// Ultra Detailed FaceNet Endpoints - iPhone Face ID Level Accuracy with Super Detailed Features

if ($action === 'process_ultra_detailed_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isset($_SESSION['user'])) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $base64Image = $_POST['image'] ?? '';

    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }

    $result = processUltraDetailedAttendance($base64Image);

    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Ultra detailed attendance processing failed'], 500);
    }
}

if ($action === 'get_ultra_detailed_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }

    $stats = getUltraDetailedPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get ultra detailed performance stats'], 500);
    }
}
