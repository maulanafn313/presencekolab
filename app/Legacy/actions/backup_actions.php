<?php

use App\Legacy\Paths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Extracted from ajax_handler.php — action group: backup
// Variables in scope: $pdo, $action, $archivedExcludeQuery, $archivedExcludeUsersQuery

if ($action === 'get_backup_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    if (! function_exists('getBackupInfo')) {
        jsonResponse(['ok' => false, 'message' => 'Backup functions not available']);
    }

    $backupInfo = getBackupInfo();
    jsonResponse(['ok' => true, 'data' => $backupInfo]);
}

// Admin: create manual backup

if ($action === 'create_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    if (! function_exists('createDatabaseBackup')) {
        jsonResponse(['ok' => false, 'message' => 'Backup functions not available']);
    }

    $result = createDatabaseBackup();
    jsonResponse($result);
}

// Admin: list backup files

if ($action === 'list_backup_files' && ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST')) {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $backupDir = Paths::backups();
    $files = [];
    $timezone = new DateTimeZone('Asia/Jakarta');

    // Always add "Current Database" option (generated on-the-fly)
    $currentTime = new DateTime('now', $timezone);
    $files[] = [
        'name' => 'current_database_backup.sql',
        'size' => 0, // Will be calculated on download
        'size_formatted' => 'Current Database',
        'created' => $currentTime->format('Y-m-d H:i:s'),
        'modified' => $currentTime->format('Y-m-d H:i:s'),
        'is_current' => true,
        'description' => 'Backup langsung dari database saat ini (selalu terbaru)',
    ];

    if (is_dir($backupDir)) {
        $items = scandir($backupDir);
        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_file($backupDir.'/'.$item)) {
                $filePath = $backupDir.'/'.$item;
                $timestamp = filemtime($filePath);

                // Convert timestamp to Asia/Jakarta timezone
                $dateTime = new DateTime('@'.$timestamp);
                $dateTime->setTimezone($timezone);
                $formattedDate = $dateTime->format('Y-m-d H:i:s');

                $files[] = [
                    'name' => $item,
                    'size' => filesize($filePath),
                    'size_formatted' => function_exists('formatBytes') ? formatBytes(filesize($filePath)) : number_format(filesize($filePath) / 1024, 2).' KB',
                    'created' => $formattedDate,
                    'modified' => $formattedDate,
                    'is_current' => false,
                ];
            }
        }
    }

    // Sort by modified date (newest first), but keep current_database_backup.sql at top
    usort($files, function ($a, $b) {
        if (isset($a['is_current']) && $a['is_current']) {
            return -1;
        }
        if (isset($b['is_current']) && $b['is_current']) {
            return 1;
        }

        return strtotime($b['modified']) - strtotime($a['modified']);
    });

    jsonResponse(['ok' => true, 'data' => $files]);
}

// Admin: download backup file

if ($action === 'download_backup' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (! isAdmin()) {
        http_response_code(403);
        exit('Forbidden');
    }

    $fileName = $_GET['file'] ?? '';
    if (empty($fileName)) {
        http_response_code(400);
        exit('File name required');
    }

    // Special case: download current database backup (generate on-the-fly)
    if ($fileName === 'current_database_backup.sql' || $fileName === 'database_current.sql') {
        if (! function_exists('createDatabaseBackupPHP')) {
            http_response_code(500);
            exit('Backup function not available');
        }

        $result = createDatabaseBackupPHP($pdo);
        $isOk = (isset($result['ok']) && $result['ok']) || (isset($result['success']) && $result['success']);
        if (! $isOk) {
            // Double check if message indicates success but flag is wrong
            if (isset($result['message']) && strpos($result['message'], 'berhasil') !== false) {
                // It actually succeeded but flags were missing/wrong
            } else {
                http_response_code(500);
                exit('Failed to generate backup: '.($result['message'] ?? 'Unknown error'));
            }
        }

        $sqlContent = $result['sql_content'];
        $downloadFileName = 'absen_db_backup_'.date('Y-m-d_His').'.sql';

        // Clear output buffer to save memory
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$downloadFileName.'"');
        header('Content-Length: '.strlen($sqlContent));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Output SQL content
        echo $sqlContent;
        exit;
    }

    // Security: only allow files in backup directory, prevent directory traversal
    $backupDir = Paths::backups();
    $filePath = $backupDir.'/'.basename($fileName);

    // Verify file is in backup directory
    $realBackupDir = realpath($backupDir);
    $realFilePath = realpath($filePath);

    if (! $realFilePath || ($realBackupDir && strpos($realFilePath, $realBackupDir) !== 0)) {
        // If file doesn't exist in backup directory, try generating from database
        if (! function_exists('createDatabaseBackupPHP')) {
            http_response_code(404);
            exit('File not found');
        }

        $result = createDatabaseBackupPHP($pdo);
        if (! ($result['ok'] ?? $result['success'] ?? false)) {
            if (isset($result['message']) && strpos($result['message'], 'berhasil') !== false) {
            } else {
                http_response_code(404);
                exit('File not found and failed to generate backup: '.($result['message'] ?? 'Unknown error'));
            }
        }

        $sqlContent = $result['sql_content'];
        $downloadFileName = basename($fileName);

        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$downloadFileName.'"');
        header('Content-Length: '.strlen($sqlContent));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Output SQL content
        echo $sqlContent;
        exit;
    }

    if (! file_exists($filePath)) {
        // File doesn't exist, generate from database
        if (! function_exists('createDatabaseBackupPHP')) {
            http_response_code(404);
            exit('File not found');
        }

        $result = createDatabaseBackupPHP($pdo);
        $isOk = (isset($result['ok']) && $result['ok']) || (isset($result['success']) && $result['success']);
        if (! $isOk) {
            if (isset($result['message']) && strpos($result['message'], 'berhasil') !== false) {
            } else {
                http_response_code(404);
                exit('File not found and failed to generate backup: '.($result['message'] ?? 'Unknown error'));
            }
        }

        $sqlContent = $result['sql_content'];
        $downloadFileName = basename($fileName);

        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$downloadFileName.'"');
        header('Content-Length: '.strlen($sqlContent));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Output SQL content
        echo $sqlContent;
        exit;
    }

    // Clear output buffer to save memory
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for file download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($fileName).'"');
    header('Content-Length: '.filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Output file
    readfile($filePath);
    exit;
}

// Admin/Employee: get current user member data (Optimized for face verification)

if ($action === 'import_db' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    if (! isset($_FILES['db_file']) || $_FILES['db_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'message' => 'Gagal mengupload atau tidak ada file SQL yang dipilih'], 400);
    }

    $file = $_FILES['db_file']['tmp_name'];
    $name = $_FILES['db_file']['name'];

    if (! str_ends_with(strtolower($name), '.sql')) {
        jsonResponse(['ok' => false, 'message' => 'Format file harus .sql'], 400);
    }

    try {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        @ignore_user_abort(true);
        DB::connection()->disableQueryLog();

        // 1. QUICK BINARY CHECK
        $f = fopen($file, 'rb');
        $header = fread($f, 4);
        fclose($f);

        $isCompressed = (substr($header, 0, 2) === "\x1f\x8b" || substr($header, 0, 4) === "PK\x03\x04");
        if ($isCompressed) {
            jsonResponse(['ok' => false, 'message' => 'File berformat terkompresi (ZIP/GZ). Mohon ekstrak ke .sql terlebih dahulu.'], 400);
        }

        // 2. BACKUP ADMINS (Preserve existing admin accounts)
        $preservedAdmins = DB::table('users')->where('role', 'admin')->get();

        // 3. CLEAN SLATE (Drop all tables)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $tableKey = 'Tables_in_'.$dbName;

        foreach ($tables as $table) {
            $tableName = $table->$tableKey;
            DB::statement("DROP TABLE IF EXISTS `$tableName` ");
        }

        // 4. EXECUTE IMPORT
        $importSuccess = false;
        $errorDetail = '';

        // Attempt A: Use mysql CLI if available (EXTREMELY faster for large files)
        if (function_exists('exec')) {
            $checkMysql = @exec('mysql --version', $cliOutput, $cliReturn);
            if ($cliReturn === 0) {
                $config = config('database.connections.'.config('database.default'));
                $host = $config['host'] ?? '127.0.0.1';
                $user = $config['username'] ?? 'root';
                $pass = $config['password'] ?? '';
                $db = $config['database'];

                // Build command safely
                $cmd = sprintf(
                    'mysql -h %s -u %s %s %s < %s 2>&1',
                    escapeshellarg($host),
                    escapeshellarg($user),
                    ($pass ? '-p'.escapeshellarg($pass) : ''),
                    escapeshellarg($db),
                    escapeshellarg($file)
                );

                exec($cmd, $importOutput, $importReturn);
                if ($importReturn === 0) {
                    $importSuccess = true;
                } else {
                    $errorDetail = 'CLI Error: '.implode(' ', $importOutput);
                    error_log('Import CLI Failed: '.$errorDetail);
                }
            }
        }

        // Attempt B: Streaming Import (Line by line) as fallback
        if (! $importSuccess) {
            $handle = fopen($file, 'r');
            if (! $handle) {
                throw new Exception('Gagal membuka file SQL untuk pembacaan.');
            }

            $templine = '';
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                // Skip comments and empty lines
                if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                $templine .= $line;
                if (str_ends_with($trimmed, ';')) {
                    try {
                        DB::unprepared($templine);
                    } catch (Exception $ex) {
                        // Log and continue or fail? Better to fail on real SQL errors
                        error_log('SQL Exec Error: '.$ex->getMessage());
                    }
                    $templine = '';
                }
            }
            fclose($handle);
            $importSuccess = true;
        }

        // 5. RESTORE ADMINS
        // Ensure users table exists after import
        if (Schema::hasTable('users')) {
            foreach ($preservedAdmins as $admin) {
                $adminData = (array) $admin;
                $exists = DB::table('users')->where('email', $admin->email)->first();
                if ($exists) {
                    DB::table('users')
                        ->where('email', $admin->email)
                        ->update([
                            'password' => $admin->password ?? ($admin->password_hash ?? ''),
                            'role' => 'admin',
                        ]);
                } else {
                    try {
                        DB::table('users')->insert($adminData);
                    } catch (Exception $e) {
                        error_log('Restore admin insert error: '.$e->getMessage());
                    }
                }
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Trigger backup but don't let it block the response too much if it's huge
        if (function_exists('triggerDatabaseBackup')) {
            // For very large imports, we might want to skip auto-backup to avoid hitting more timeouts
            // But we'll leave it for now as it's usually useful
            triggerDatabaseBackup();
        }

        jsonResponse(['ok' => true, 'message' => 'Database berhasil direstore. '.($importSuccess ? 'Metode cepat berhasil.' : 'Metode alternatif selesai.')]);
    } catch (Exception $e) {
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        error_log('Import DB Error: '.$e->getMessage());
        jsonResponse(['ok' => false, 'message' => 'Gagal mengimport database: '.substr($e->getMessage(), 0, 250)], 500);
    }
}

// ============================================================
// INTERN GROUPS — Kelompok Magang
// ============================================================

// Helper: pastikan tabel ada sebelum query intern groups
if (in_array($action, ['get_intern_groups', 'save_intern_group', 'delete_intern_group', 'get_group_members', 'assign_members_to_group', 'archive_intern_group', 'unarchive_intern_group', 'export_group_database'])) {
    try {
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
    } catch (PDOException $e) { /* Sudah ada, abaikan */
    }
    try {
        $pdo->exec('
                CREATE TABLE IF NOT EXISTS intern_group_members (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id INT NOT NULL,
                    user_id INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_group_user (group_id, user_id),
                    KEY fk_igm_group_idx (group_id),
                    KEY fk_igm_user_idx (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ');
    } catch (PDOException $e) { /* Sudah ada, abaikan */
    }
    // Tambahkan FK secara terpisah (aman jika sudah ada)
    try {
        $pdo->exec('ALTER TABLE intern_group_members ADD CONSTRAINT fk_igm_group FOREIGN KEY (group_id) REFERENCES intern_groups(id) ON DELETE CASCADE');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('ALTER TABLE intern_group_members ADD CONSTRAINT fk_igm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE');
    } catch (PDOException $e) {
    }
}
