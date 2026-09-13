<?php

use App\Legacy\Paths;
use App\Services\DatabaseBackup;

/**
 * Backup Helper Functions
 */
if (! function_exists('createDatabaseBackup')) {
    function createDatabaseBackup()
    {
        return app(DatabaseBackup::class)->create();
    }
}

if (! function_exists('cleanOldBackups')) {
    function cleanOldBackups($backupDir, $currentFilename)
    {
        // Keep old integrations callable; pruning is now an explicit maintenance operation.
        // Normal attendance requests must never delete recovery points.
    }
}

if (! function_exists('getBackupInfo')) {
    function getBackupInfo()
    {
        $backupDir = Paths::backups();
        $files = [];

        if (file_exists($backupDir)) {
            $scan = scandir($backupDir);
            foreach ($scan as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $filePath = $backupDir.'/'.$file;
                $files[] = [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'date' => date('Y-m-d H:i:s', filemtime($filePath)),
                ];
            }
        }

        // Sort by date desc
        usort($files, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return [
            'count' => count($files),
            'files' => $files,
            'last_backup' => count($files) > 0 ? $files[0]['date'] : 'Belum pernah',
        ];
    }
}

if (! function_exists('createDatabaseBackupPHP')) {
    function createDatabaseBackupPHP($pdo)
    {
        // Increase memory limit for large DB dumps
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        try {
            $tables = [];
            $result = $pdo->query('SHOW TABLES');
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }

            $sql = "-- Database Backup (PHP Fallback)\n";
            $sql .= '-- Date: '.date('Y-m-d H:i:s')."\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                // Structure
                $res = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $row = $res->fetch(PDO::FETCH_NUM);
                $sql .= "\n\nDROP TABLE IF EXISTS `{$table}`;\n".$row[1].";\n\n";

                // Data
                $res = $pdo->query("SELECT * FROM `{$table}`");
                while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
                    if ($table === 'attendance') {
                        unset($row['attendance_day']);
                    }
                    $keys = array_keys($row);
                    $escaped_keys = array_map(function ($k) {
                        return "`$k`";
                    }, $keys);
                    $values = array_values($row);
                    $escaped_values = array_map(function ($v) use ($pdo) {
                        if ($v === null) {
                            return 'NULL';
                        }

                        return $pdo->quote($v);
                    }, $values);

                    $sql .= "INSERT INTO `{$table}` (".implode(', ', $escaped_keys).') VALUES ('.implode(', ', $escaped_values).");\n";
                }
            }

            $sql .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

            return [
                'ok' => true,
                'success' => true,
                'sql_content' => $sql,
                'message' => 'Backup berhasil dibuat (PHP Fallback)',
            ];
        } catch (Exception $e) {
            return [
                'ok' => false,
                'success' => false,
                'message' => 'Gagal membuat backup via PHP: '.$e->getMessage(),
            ];
        }
    }
}
