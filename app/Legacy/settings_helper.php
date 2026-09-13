<?php

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1');
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetch();
    $cache[$key] = $result ? $result['setting_value'] : $default;

    return $cache[$key];
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

/**
 * Format bytes menjadi string yang mudah dibaca (KB, MB, GB, dsb.)
 */
if (! function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2): string
    {
        if ($bytes === null || $bytes === false) {
            return '0 B';
        }
        $bytes = (int) $bytes;
        if ($bytes === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = floor(log($bytes, 1024));
        $exp = min($exp, count($units) - 1);

        return round($bytes / pow(1024, $exp), $precision).' '.$units[$exp];
    }
}

/**
 * Helper function untuk memanggil backup database setelah operasi yang mengubah data.
 *
 * PERFORMANCE FIX: Backup sekarang NON-BLOCKING dan dibatasi 1x per hari.
 * Sebelumnya: backup sinkron dipanggil 24+ kali (memblokir request 10-60 detik!).
 * Sekarang: cek flag file harian, jika perlu → jalankan di background (fire-and-forget).
 */
