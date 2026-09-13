<?php

use App\Jobs\CreateDatabaseBackup;

// Extracted from core.php — do not edit the original functions here without updating core.php require.

function triggerDatabaseBackup(): void
{
    if (app()->environment('testing') || ! config('backup.auto_enabled')) {
        return;
    }
    CreateDatabaseBackup::dispatch()->afterCommit();
}

try {
    $pdo = getPdo();

    if (! empty($_SESSION['user']['id'])) {
        $authCheck = $pdo->prepare('SELECT id, role, nim, nama, email, password FROM users WHERE id = ?');
        $authCheck->execute([$_SESSION['user']['id']]);
        $freshUser = $authCheck->fetch();
        if (! $freshUser || ! hash_equals(hash('sha256', $freshUser['password']), $_SESSION['auth_password_fingerprint'] ?? '')) {
            unset($_SESSION['user'], $_SESSION['auth_password_fingerprint']);
            if (function_exists('session')) {
                session()->forget(['user', 'auth_password_fingerprint']);
            }
        } else {
            unset($freshUser['password']);
            $_SESSION['user'] = array_merge($_SESSION['user'], $freshUser);
        }
    }

    // PERFORMANCE FIX: Guard expensive boot queries with session flags.
    // Previously these ran on EVERY page load, causing significant DB overhead.
    $bootDoneKey = '_app_boot_done_v4'; // bumped to v4: adds intern_groups auto-create
    $needsBoot = false; // Schema/seed operations must run through explicit CLI maintenance.

    if ($needsBoot) {
        // Auto-create cache table if it doesn't exist
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'kpi_monthly_cache'");
            if ($checkTable->rowCount() == 0) {
                ensureSchema($pdo);
            }
        } catch (Exception $e) {
            error_log('Failed to check or auto-initialize kpi_monthly_cache: '.$e->getMessage());
        }

        // Auto-create intern_groups tables if they don't exist (added in v2)
        try {
            $checkInternGroups = $pdo->query("SHOW TABLES LIKE 'intern_groups'");
            if ($checkInternGroups->rowCount() == 0) {
                $pdo->exec('
                    CREATE TABLE IF NOT EXISTS intern_groups (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        nama VARCHAR(255) NOT NULL,
                        tanggal_mulai DATE NOT NULL,
                        tanggal_selesai DATE NOT NULL,
                        is_archived TINYINT(1) NOT NULL DEFAULT 0,
                        archived_at DATETIME NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');
                $pdo->exec('
                    CREATE TABLE IF NOT EXISTS intern_group_members (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        group_id INT NOT NULL,
                        user_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY unique_group_user (group_id, user_id),
                        CONSTRAINT fk_igm_group FOREIGN KEY (group_id) REFERENCES intern_groups(id) ON DELETE CASCADE,
                        CONSTRAINT fk_igm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ');
            }
        } catch (Exception $e) {
            error_log('Failed to auto-initialize intern_groups tables: '.$e->getMessage());
        }

        // Seed national holidays once per session
        try {
            seedNationalHolidays($pdo);
        } catch (Exception $e) {
            error_log('Failed to seed national holidays: '.$e->getMessage());
        }

        // Clear KPI cache once to migrate date lists (one-time migration)
        try {
            $flag = getSetting($pdo, 'kpi_cache_dates_cleared_v1');
            if ($flag !== '1') {
                clearAllKpiCache($pdo);
                $setFlag = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES ('kpi_cache_dates_cleared_v1', '1', 'Flag indicating cache cleared for date columns migration') ON DUPLICATE KEY UPDATE setting_value = '1'");
                $setFlag->execute();
            }
        } catch (Exception $e) {
            error_log('Failed to run cache migration: '.$e->getMessage());
        }

        // Mark boot as done for this session
        $_SESSION[$bootDoneKey] = true;
    }

    // PERFORMANCE: Only run schema verification if explicitly requested
    if (isset($_GET['install_db'])) {
        http_response_code(403);
        exit('Instalasi database melalui web dinonaktifkan.');
        ensureSchema($pdo);

        // Verify that the attendance table has all required columns
        if (! verifyAttendanceTable($pdo)) {
            error_log('Attendance table verification failed - attempting to fix schema');
            ensureSchema($pdo); // Try to fix the schema again
            if (! verifyAttendanceTable($pdo)) {
                throw new Exception('Failed to create proper attendance table schema');
            }
        }

        seedAdmin($pdo, $DEFAULT_ADMIN_EMAIL, $DEFAULT_ADMIN_PASSWORD);
        seedDefaultSettings($pdo);
    }
} catch (Exception $e) {
    error_log('Database initialization failed: '.$e->getMessage());
    if (isset($_GET['ajax'])) {
        jsonResponse(['error' => 'Database connection failed'], 500);
    }
    // For non-AJAX requests, we'll let the page load but show an error
}

// Helper function for JSON response
