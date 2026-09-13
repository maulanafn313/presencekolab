<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

// Function to check if base64 image data is too large
function checkImageSize($dataUrl, $maxSizeMB = 5)
{
    if (! $dataUrl || strpos($dataUrl, 'data:image/') !== 0) {
        return ['valid' => true, 'message' => '']; // Not a valid image data URL, skip check
    }

    // Extract base64 data from data URL
    $data = explode(',', $dataUrl, 2);
    if (count($data) !== 2) {
        return ['valid' => true, 'message' => '']; // Invalid format, skip check
    }

    $imageData = base64_decode($data[1]);
    if ($imageData === false) {
        return ['valid' => true, 'message' => '']; // Failed to decode, skip check
    }

    $sizeMB = strlen($imageData) / (1024 * 1024);
    if ($sizeMB > $maxSizeMB) {
        return ['valid' => false, 'message' => "Ukuran gambar terlalu besar ($sizeMB MB). Maksimal $maxSizeMB MB."];
    }

    return ['valid' => true, 'message' => ''];
}

/**
 * Menyimpan gambar Base64 ke sistem file untuk mengurangi beban database.
 *
 * @param  string  $base64String  - Data URL gambar
 * @param  string  $subDir  - Subdirektori di dalam public/storage/
 * @return string - Path relatif ke file yang disimpan, atau string asli jika gagal
 */
function saveBase64Image($base64String, $subDir)
{
    if (! $base64String || strpos($base64String, 'data:image/') !== 0) {
        return $base64String; // Bukan base64 atau kosong
    }

    try {
        $parts = explode(',', $base64String, 2);
        if (count($parts) !== 2) {
            error_log('saveBase64Image: Invalid base64 format (missing comma)');

            return $base64String;
        }

        $data = base64_decode($parts[1]);
        if (! $data) {
            error_log('saveBase64Image: Failed to decode base64 data');

            return $base64String;
        }

        // Tentukan ekstensi
        $extension = 'jpg';
        if (strpos($parts[0], 'image/png') !== false) {
            $extension = 'png';
        } elseif (strpos($parts[0], 'image/webp') !== false) {
            $extension = 'webp';
        }

        $fileName = uniqid().'_'.time().'.'.$extension;
        $targetDir = public_path('storage/'.$subDir);

        if (! file_exists($targetDir)) {
            if (! mkdir($targetDir, 0755, true)) {
                error_log("saveBase64Image: Failed to create directory $targetDir");

                return $base64String;
            }
        }

        $filePath = $targetDir.'/'.$fileName;
        if (file_put_contents($filePath, $data) === false) {
            error_log("saveBase64Image: Failed to write file to $filePath");

            return $base64String;
        }

        $relativePath = 'storage/'.$subDir.'/'.$fileName;
        error_log("saveBase64Image: Success! Saved to $relativePath");

        return $relativePath;
    } catch (Exception $e) {
        error_log('saveBase64Image: Exception - '.$e->getMessage());

        return $base64String;
    }
}

/**
 * Helper to get the correct URL for a user's avatar
 * Supports: Data URL, storage path, raw filename, and raw base64.
 */
function getAvatarUrl($foto, $nama = 'A')
{
    $default = 'https://ui-avatars.com/api/?background=4f46e5&color=fff&name='.urlencode($nama).'&size=128';
    if (empty($foto)) {
        return $default;
    }

    // If it's a data URL (Base64)
    if (strpos($foto, 'data:') === 0) {
        return $foto;
    }

    // If it's a path starting with storage/
    if (strpos($foto, 'storage/') === 0) {
        if (strpos($foto, 'storage/users/') === 0) return \App\Services\PrivateMedia::url(basename($foto));
        return '/'.$foto;
    }

    // If it's just a filename, assume it's in storage/users/
    if (strpos($foto, '.') !== false && strpos($foto, '/') === false) {
        return \App\Services\PrivateMedia::url($foto);
    }

    // If it's raw Base64 without data prefix (backward compatibility)
    if (strlen($foto) > 500) {
        return 'data:image/png;base64,'.$foto;
    }

    return $default;
}

// Function to get first name (first word) from full name
function getFirstName($fullName)
{
    if (empty($fullName)) {
        return '';
    }
    $nameParts = explode(' ', trim($fullName));

    return $nameParts[0];
}

// Helper function to convert memory limit string to bytes
function return_bytes($val)
{
    $val = trim($val);
    if (empty($val)) {
        return 0;
    }
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int) $val;
    switch ($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }

    return $val;
}

// Google Authenticator Helper Functions
