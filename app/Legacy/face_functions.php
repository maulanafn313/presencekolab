<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function generateFaceEmbedding($base64Image)
{
    try {
        $data = [
            'action' => 'generate_embedding',
            'image' => $base64Image,
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
                return $result['data']['embedding'];
            }
        }

        error_log('FaceNet embedding generation failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error generating face embedding: '.$e->getMessage());

        return null;
    }
}

function recognizeFace($base64Image, $threshold = 1.0)
{
    try {
        $data = [
            'action' => 'recognize_face',
            'image' => $base64Image,
            'threshold' => $threshold,
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
                return $result['data'];
            }
        }

        error_log('FaceNet recognition failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error recognizing face: '.$e->getMessage());

        return null;
    }
}

function saveFaceEmbedding($userId, $embedding)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare('UPDATE users SET face_embedding = ?, face_embedding_updated = NOW() WHERE id = ?');
        $stmt->execute([json_encode($embedding), $userId]);

        return true;
    } catch (Exception $e) {
        error_log('Error saving face embedding: '.$e->getMessage());

        return false;
    }
}

function getFaceEmbeddings()
{
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, nim, nama, face_embedding FROM users WHERE role='pegawai' AND face_embedding IS NOT NULL");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $embeddings = [];
        foreach ($users as $user) {
            $embedding = json_decode($user['face_embedding'], true);
            if ($embedding) {
                $embeddings[$user['nim']] = $embedding;
            }
        }

        return $embeddings;
    } catch (Exception $e) {
        error_log('Error getting face embeddings: '.$e->getMessage());

        return [];
    }
}

function processAttendanceWithFaceNet($base64Image)
{
    try {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('FaceNet attendance processing failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error processing attendance with FaceNet: '.$e->getMessage());

        return null;
    }
}

// Enhanced FaceNet Functions
function generateEnhancedFaceEmbedding($base64Image)
{
    try {
        $data = [
            'action' => 'generate_enhanced_embedding',
            'image' => $base64Image,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_enhanced_api.php');
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
                return $result['data'];
            }
        }

        error_log('Enhanced FaceNet embedding generation failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error generating enhanced face embedding: '.$e->getMessage());

        return null;
    }
}

// High Accuracy FaceNet Functions
function processHighAccuracyAttendance($base64Image, $userId = null)
{
    try {
        $data = [
            'action' => 'process_high_accuracy_attendance',
            'image' => $base64Image,
        ];

        if ($userId !== null) {
            $data['user_id'] = $userId;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_high_accuracy_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('High accuracy attendance processing failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error processing high accuracy attendance: '.$e->getMessage());

        return null;
    }
}

// Optimized FaceNet Functions - iPhone-like Performance
function processOptimizedAttendance($base64Image, $threshold = 0.5)
{
    try {
        $data = [
            'action' => 'process_attendance_optimized',
            'image' => $base64Image,
            'threshold' => $threshold,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Faster timeout for optimized service

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Optimized attendance processing failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error processing optimized attendance: '.$e->getMessage());

        return null;
    }
}

function recognizeFaceOptimized($base64Image, $threshold = 0.5)
{
    try {
        $data = [
            'action' => 'recognize_face_optimized',
            'image' => $base64Image,
            'threshold' => $threshold,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Optimized face recognition failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error in optimized face recognition: '.$e->getMessage());

        return null;
    }
}

function generateOptimizedEmbedding($base64Image)
{
    try {
        $data = [
            'action' => 'generate_embedding_optimized',
            'image' => $base64Image,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Optimized embedding generation failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error generating optimized embedding: '.$e->getMessage());

        return null;
    }
}

function getOptimizedPerformanceStats()
{
    try {
        $data = ['action' => 'get_performance_stats'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Failed to get optimized performance stats: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error getting optimized performance stats: '.$e->getMessage());

        return null;
    }
}

// Ultra Accurate FaceNet Functions - Maximum Accuracy with Ultra-Fast Response
function processUltraAccurateAttendance($base64Image, $validationLevel = 'normal')
{
    try {
        $data = [
            'action' => 'process_attendance_ultra_accurate',
            'image' => $base64Image,
            'validation_level' => $validationLevel,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_ultra_accurate_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Ultra-fast timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Ultra accurate attendance processing failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error processing ultra accurate attendance: '.$e->getMessage());

        return null;
    }
}

function getUltraAccuratePerformanceStats()
{
    try {
        $data = ['action' => 'get_performance_stats'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_ultra_accurate_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Failed to get ultra accurate performance stats: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error getting ultra accurate performance stats: '.$e->getMessage());

        return null;
    }
}

// Direct iPhone-Level Accurate FaceNet Functions - Maximum Accuracy with Direct Processing
function processIPhoneLevelAttendance($base64Image)
{
    try {
        // Direct Python execution without API
        $command = 'python facenet_iphone_accurate_service.py recognize_face '.escapeshellarg($base64Image);

        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;
        exec($command.' 2>&1', $output, $returnCode);
        $executionTime = microtime(true) - $startTime;

        if ($returnCode === 0 && ! empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result && $result['success']) {
                // Add execution time to result
                $result['execution_time'] = $executionTime;

                return $result;
            }
        }

        error_log('Direct iPhone-level processing failed: '.implode("\n", $output));

        return null;
    } catch (Exception $e) {
        error_log('Error in direct iPhone-level processing: '.$e->getMessage());

        return null;
    }
}

function getIPhoneLevelPerformanceStats()
{
    try {
        $data = ['action' => 'get_performance_stats'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_iphone_accurate_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Failed to get iPhone-level performance stats: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error getting iPhone-level performance stats: '.$e->getMessage());

        return null;
    }
}

// Ultra Detailed FaceNet Functions - iPhone Face ID Level Accuracy with Super Detailed Features
function processUltraDetailedAttendance($base64Image)
{
    try {
        // Direct Python execution without API for maximum speed
        $command = 'python facenet_ultra_detailed_service.py process_attendance_ultra_detailed '.escapeshellarg($base64Image);

        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;
        exec($command.' 2>&1', $output, $returnCode);
        $executionTime = microtime(true) - $startTime;

        if ($returnCode === 0 && ! empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result && $result['success']) {
                // Add execution time to result
                $result['execution_time'] = $executionTime;

                return $result;
            }
        }

        error_log('Ultra detailed attendance processing failed: '.implode("\n", $output));

        return null;
    } catch (Exception $e) {
        error_log('Error processing ultra detailed attendance: '.$e->getMessage());

        return null;
    }
}

function getUltraDetailedPerformanceStats()
{
    try {
        $command = 'python facenet_ultra_detailed_service.py get_performance_stats';

        $output = [];
        $returnCode = 0;
        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode === 0 && ! empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result) {
                return $result;
            }
        }

        error_log('Failed to get ultra detailed performance stats: '.implode("\n", $output));

        return null;
    } catch (Exception $e) {
        error_log('Error getting ultra detailed performance stats: '.$e->getMessage());

        return null;
    }
}

function generateHighAccuracyEmbedding($base64Image, $userId)
{
    try {
        $data = [
            'action' => 'generate_high_accuracy_embedding',
            'image' => $base64Image,
            'user_id' => $userId,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_high_accuracy_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('High accuracy embedding generation failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error generating high accuracy embedding: '.$e->getMessage());

        return null;
    }
}

function getHighAccuracyPerformanceStats()
{
    try {
        $data = ['action' => 'get_performance_stats'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_high_accuracy_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Failed to get high accuracy performance stats: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error getting high accuracy performance stats: '.$e->getMessage());

        return null;
    }
}

function recognizeEnhancedFace($base64Image, $threshold = 1.0)
{
    try {
        $data = [
            'action' => 'recognize_enhanced_face',
            'image' => $base64Image,
            'threshold' => $threshold,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/facenet_enhanced_api.php');
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
                return $result['data'];
            }
        }

        error_log('Enhanced FaceNet recognition failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error recognizing enhanced face: '.$e->getMessage());

        return null;
    }
}

function saveEnhancedFaceEmbedding($userId, $enhancedEmbedding)
{
    global $pdo;
    try {
        $baseEmbedding = json_encode($enhancedEmbedding['base_embedding'] ?? []);
        $advancedFeatures = json_encode($enhancedEmbedding['advanced_features'] ?? []);
        $facialGeometry = json_encode($enhancedEmbedding['advanced_features']['geometry'] ?? []);
        $featureVector = json_encode($enhancedEmbedding['advanced_features']['feature_vector'] ?? []);

        $stmt = $pdo->prepare('
            UPDATE users SET 
                face_embedding = ?, 
                advanced_features = ?,
                facial_geometry = ?,
                feature_vector = ?,
                face_embedding_updated = NOW() 
            WHERE id = ?
        ');
        $stmt->execute([$baseEmbedding, $advancedFeatures, $facialGeometry, $featureVector, $userId]);

        return true;
    } catch (Exception $e) {
        error_log('Error saving enhanced face embedding: '.$e->getMessage());

        return false;
    }
}

function processEnhancedAttendance($base64Image)
{
    try {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }

        error_log('Enhanced FaceNet attendance processing failed: '.$response);

        return null;
    } catch (Exception $e) {
        error_log('Error processing enhanced attendance: '.$e->getMessage());

        return null;
    }
}

// KPI Calculation Functions
