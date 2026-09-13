<?php

use Illuminate\Support\Facades\DB;

// session_start(); // Handled by Laravel middleware in PageController
if (session_status() === PHP_SESSION_NONE && ! headers_sent()) {
    session_start();
}
// Bridge Laravel session to $_SESSION for legacy compatibility
if (function_exists('session') && session()->isStarted()) {
    foreach (session()->all() as $key => $value) {
        $_SESSION[$key] = $value;
    }
}

date_default_timezone_set('Asia/Jakarta');

// Global action variable from GET or POST
$action = $_REQUEST['ajax'] ?? $_REQUEST['action'] ?? null;

// Production-optimized PHP settings
ini_set('log_errors', '1');
ini_set('error_log', storage_path('logs/php-error.log'));
ini_set('log_errors_max_len', '1024'); // Limit error log entry size
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Never show errors in production

// Increase limits for large datasets (production hosting)
@ini_set('memory_limit', '256M'); // Increase from default 128M
@ini_set('max_execution_time', '60'); // Prevent infinite hangs

// Bootstrap selesai — log ini dihapus karena dipanggil di setiap request

// Include helpers
require_once __DIR__.'/backup_helper.php';

// Load Composer autoloader for Google Authenticator
if (file_exists(dirname(__DIR__, 2).'/vendor/autoload.php')) {
    require_once dirname(__DIR__, 2).'/vendor/autoload.php';
}

// ----- CONFIG -----
// Change if needed for your XAMPP/MySQL setup
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'laravel_absen_db';

// Include database backup functions (if exists)
if (file_exists('database_backup.php')) {
    require_once 'database_backup.php';
}

// Default admin (seeded if not exists)
$DEFAULT_ADMIN_EMAIL = 'admin@example.com';
$DEFAULT_ADMIN_PASSWORD = 'admin123';

/**
 * Cleanup old attendance photos after 10 days to save storage.
 * Keeps expressions for historical visualization.
 */
function cleanupOldAttendancePhotos(PDO $pdo): int
{
    $tenDaysAgo = date('Y-m-d', strtotime('-14 days'));

    // Set photos, screenshots, and landmarks to NULL if date is older than 14 days (approx 10 working days)
    // We keep the expression to show labels
    $stmt = $pdo->prepare('
        UPDATE attendance 
        SET foto_masuk = NULL, 
            screenshot_masuk = NULL,
            landmark_masuk = NULL, 
            foto_pulang = NULL, 
            screenshot_pulang = NULL,
            landmark_pulang = NULL 
        WHERE DATE(jam_masuk_iso) < :date
        AND (foto_masuk IS NOT NULL OR screenshot_masuk IS NOT NULL OR foto_pulang IS NOT NULL OR screenshot_pulang IS NOT NULL OR landmark_masuk IS NOT NULL OR landmark_pulang IS NOT NULL)
    ');
    $stmt->execute([':date' => $tenDaysAgo]);

    return $stmt->rowCount();
}

/**
 * Translate face expressions to Indonesian labels
 */
function translateExpression(?string $expression): string
{
    if (empty($expression) || $expression === 'neutral') {
        return 'Netral';
    }

    $map = [
        'neutral' => 'Netral',
        'happy' => 'Senang',
        'sad' => 'Sedih',
        'angry' => 'Marah',
        'fearful' => 'Takut',
        'disgusted' => 'Jijik',
        'surprised' => 'Terkejut',
    ];

    $lower = strtolower($expression);

    return $map[$lower] ?? ucfirst($expression);
}

/**
 * Get CSS classes for expression labels
 */
function getExpressionClass(?string $expression): string
{
    if (empty($expression) || $expression === 'neutral') {
        return 'bg-blue-50 text-blue-600 border border-blue-100';
    }

    $map = [
        'neutral' => 'bg-blue-50 text-blue-600 border border-blue-100',
        'happy' => 'bg-green-50 text-green-600 border border-green-100',
        'sad' => 'bg-gray-50 text-gray-600 border border-gray-100',
        'angry' => 'bg-red-50 text-red-600 border border-red-100',
        'fearful' => 'bg-purple-50 text-purple-600 border border-purple-100',
        'disgusted' => 'bg-orange-50 text-orange-600 border border-orange-100',
        'surprised' => 'bg-yellow-50 text-yellow-600 border border-yellow-100',
    ];

    $lower = strtolower($expression);

    return $map[$lower] ?? 'bg-gray-50 text-gray-600 border border-gray-100';
}

// ----- DB SETUP -----
function getPdo(): PDO
{
    if (function_exists('app')) {
        $connection = DB::connection()->getPdo();
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $connection;
    }
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    // Use env() if available (Laravel environment), otherwise fallback to globals
    $host = function_exists('env') ? env('DB_HOST', $DB_HOST) : $DB_HOST;
    $name = function_exists('env') ? env('DB_DATABASE', $DB_NAME) : $DB_NAME;
    $user = function_exists('env') ? env('DB_USERNAME', $DB_USER) : $DB_USER;
    $pass = function_exists('env') ? env('DB_PASSWORD', $DB_PASS) : $DB_PASS;

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: '.$e->getMessage());
        throw new Exception('Gagal terhubung ke database. Silakan periksa konfigurasi database Anda.');
    }
}

function ensureSchema(PDO $pdo): void
{
    // users: role admin/pegawai, foto disimpan base64 data URL
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role ENUM('admin','pegawai') NOT NULL DEFAULT 'pegawai',
            email VARCHAR(255) NOT NULL UNIQUE,
            nim VARCHAR(100) NULL UNIQUE,
            nama VARCHAR(255) NOT NULL,
            prodi VARCHAR(255) NULL,
            startup VARCHAR(255) NULL,
                    foto_base64 LONGTEXT NULL,
                    face_embedding LONGTEXT NULL,
                    face_embedding_updated TIMESTAMP NULL,
                    advanced_features LONGTEXT NULL,
                    facial_geometry LONGTEXT NULL,
                    feature_vector LONGTEXT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // attendance
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            jam_masuk VARCHAR(20) NULL,
            jam_masuk_iso DATETIME NULL,
            ekspresi_masuk VARCHAR(50) NULL,
            foto_masuk LONGTEXT NULL,
            landmark_masuk LONGTEXT NULL,
            lokasi_masuk VARCHAR(255) NULL,
            lat_masuk DECIMAL(10,7) NULL,
            lng_masuk DECIMAL(10,7) NULL,
            jam_pulang VARCHAR(20) NULL,
            jam_pulang_iso DATETIME NULL,
            ekspresi_pulang VARCHAR(50) NULL,
            foto_pulang LONGTEXT NULL,
            landmark_pulang LONGTEXT NULL,
            lokasi_pulang VARCHAR(255) NULL,
            lat_pulang DECIMAL(10,7) NULL,
            lng_pulang DECIMAL(10,7) NULL,
            status ENUM('ontime','terlambat') DEFAULT 'ontime',
            ket ENUM('wfo','izin','sakit','alpha','wfa') DEFAULT 'wfo',
            alasan_wfa TEXT NULL,
            alasan_overtime TEXT NULL,
            lokasi_overtime VARCHAR(255) NULL,
            alasan_izin_sakit TEXT NULL,
            bukti_izin_sakit LONGTEXT NULL,
            daily_report_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // settings table for admin configuration
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // manual_holidays table for admin-defined off days (e.g., demo/disaster)
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS manual_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(date),
            CONSTRAINT fk_manual_holidays_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // employee_work_schedule table for individual work schedules
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS employee_work_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
            is_working_day BOOLEAN DEFAULT TRUE,
            start_time TIME DEFAULT '08:00:00',
            end_time TIME DEFAULT '17:00:00',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(user_id),
            CONSTRAINT fk_schedule_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_day (user_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Add missing columns if they don't exist (for existing databases)
    $requiredColumns = [
        'ekspresi_masuk' => 'ALTER TABLE attendance ADD COLUMN ekspresi_masuk VARCHAR(50) NULL AFTER jam_masuk_iso',
        'ekspresi_pulang' => 'ALTER TABLE attendance ADD COLUMN ekspresi_pulang VARCHAR(50) NULL AFTER jam_pulang_iso',
        'foto_masuk' => 'ALTER TABLE attendance ADD COLUMN foto_masuk LONGTEXT NULL AFTER ekspresi_masuk',
        'foto_pulang' => 'ALTER TABLE attendance ADD COLUMN foto_pulang LONGTEXT NULL AFTER ekspresi_pulang',
        'status' => "ALTER TABLE attendance ADD COLUMN status ENUM('ontime','terlambat') DEFAULT 'ontime' AFTER ekspresi_pulang",
        'ket' => "ALTER TABLE attendance ADD COLUMN ket ENUM('wfo','izin','sakit','alpha','wfa','overtime') DEFAULT 'wfo' AFTER status",
        'lokasi_masuk' => 'ALTER TABLE attendance ADD COLUMN lokasi_masuk VARCHAR(255) NULL AFTER foto_masuk',
        'lat_masuk' => 'ALTER TABLE attendance ADD COLUMN lat_masuk DECIMAL(10,7) NULL AFTER lokasi_masuk',
        'lng_masuk' => 'ALTER TABLE attendance ADD COLUMN lng_masuk DECIMAL(10,7) NULL AFTER lat_masuk',
        'lokasi_pulang' => 'ALTER TABLE attendance ADD COLUMN lokasi_pulang VARCHAR(255) NULL AFTER foto_pulang',
        'lat_pulang' => 'ALTER TABLE attendance ADD COLUMN lat_pulang DECIMAL(10,7) NULL AFTER lokasi_pulang',
        'lng_pulang' => 'ALTER TABLE attendance ADD COLUMN lng_pulang DECIMAL(10,7) NULL AFTER lat_pulang',
        'alasan_wfa' => 'ALTER TABLE attendance ADD COLUMN alasan_wfa TEXT NULL AFTER ket',
        'alasan_overtime' => 'ALTER TABLE attendance ADD COLUMN alasan_overtime TEXT NULL AFTER alasan_wfa',
        'lokasi_overtime' => 'ALTER TABLE attendance ADD COLUMN lokasi_overtime VARCHAR(255) NULL AFTER alasan_overtime',
        'alasan_izin_sakit' => 'ALTER TABLE attendance ADD COLUMN alasan_izin_sakit TEXT NULL AFTER lokasi_overtime',
        'bukti_izin_sakit' => 'ALTER TABLE attendance ADD COLUMN bukti_izin_sakit LONGTEXT NULL AFTER alasan_izin_sakit',
        'daily_report_id' => 'ALTER TABLE attendance ADD COLUMN daily_report_id INT NULL AFTER ket',
        'alasan_pulang_awal' => 'ALTER TABLE attendance ADD COLUMN alasan_pulang_awal TEXT NULL AFTER bukti_izin_sakit',
        'alasan_lokasi_berbeda' => 'ALTER TABLE attendance ADD COLUMN alasan_lokasi_berbeda TEXT NULL AFTER alasan_pulang_awal',
    ];

    // Add FaceNet embedding columns to users table
    $userColumns = [
        'face_embedding' => 'ALTER TABLE users ADD COLUMN face_embedding LONGTEXT NULL AFTER foto_base64',
        'face_embedding_updated' => 'ALTER TABLE users ADD COLUMN face_embedding_updated TIMESTAMP NULL AFTER face_embedding',
        'advanced_features' => 'ALTER TABLE users ADD COLUMN advanced_features LONGTEXT NULL AFTER face_embedding_updated',
        'facial_geometry' => 'ALTER TABLE users ADD COLUMN facial_geometry LONGTEXT NULL AFTER advanced_features',
        'feature_vector' => 'ALTER TABLE users ADD COLUMN feature_vector LONGTEXT NULL AFTER facial_geometry',
        'google_authenticator_secret' => 'ALTER TABLE users ADD COLUMN google_authenticator_secret VARCHAR(255) NULL AFTER password_hash',
        'password_reset_token' => 'ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(255) NULL AFTER google_authenticator_secret',
        'password_reset_expires' => 'ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL AFTER password_reset_token',
    ];

    foreach ($requiredColumns as $column => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
    }

    // Add FaceNet embedding columns to users table
    foreach ($userColumns as $column => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
    }

    // Update ket column enum to include 'overtime'
    try {
        $pdo->exec("ALTER TABLE attendance MODIFY ket ENUM('wfo','izin','sakit','alpha','wfa','overtime') DEFAULT 'wfo'");
    } catch (PDOException $e) {
        // Ignore error if column doesn't exist or enum is already correct
    }

    // Fix manual_holidays table structure if needed
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'manual_holidays'");
        if ($checkTable->rowCount() > 0) {
            // Check if created_by column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM manual_holidays LIKE 'created_by'");
            if ($checkColumn->rowCount() == 0) {
                // Add created_by column if it doesn't exist
                $pdo->exec('ALTER TABLE manual_holidays ADD COLUMN created_by INT NULL AFTER name');
                $pdo->exec('ALTER TABLE manual_holidays ADD CONSTRAINT fk_manual_holidays_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
            }
        } else {
            // Table doesn't exist, create it
            $pdo->exec('
                CREATE TABLE manual_holidays (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    created_by INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(date),
                    CONSTRAINT fk_manual_holidays_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ');
        }
    } catch (PDOException $e) {
        error_log('Error fixing manual_holidays table: '.$e->getMessage());
    }

    // Admin help requests table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_help_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            request_type ENUM('past_attendance', 'late_attendance', 'bug_report') NOT NULL,
            tanggal DATE NULL,
            jam_masuk TIME NULL,
            jam_pulang TIME NULL,
            alasan_izin TEXT NULL,
            jenis_izin ENUM('izin', 'sakit') NULL,
            bukti_izin LONGTEXT NULL,
            bukti_presensi LONGTEXT NULL,
            lokasi_presensi VARCHAR(255) NULL,
            bug_description TEXT NULL,
            bug_proof LONGTEXT NULL,
            status ENUM('pending', 'approved', 'disapproved', 'solved') DEFAULT 'pending',
            admin_note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            INDEX(status),
            CONSTRAINT fk_ahr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Migration for status ENUM in admin_help_requests
    try {
        $pdo->exec("ALTER TABLE admin_help_requests MODIFY COLUMN status ENUM('pending', 'approved', 'disapproved', 'solved') DEFAULT 'pending'");
    } catch (PDOException $e) {
    }

    // Migration: extend request_type ENUM to include diff_location_checkout
    try {
        $pdo->exec("ALTER TABLE admin_help_requests MODIFY COLUMN request_type ENUM('past_attendance', 'late_attendance', 'bug_report', 'diff_location_checkout') NOT NULL");
    } catch (PDOException $e) {
    }

    // Migration: add lat_pulang and lng_pulang columns for diff_location_checkout
    try {
        $pdo->exec('ALTER TABLE admin_help_requests ADD COLUMN lat_pulang DECIMAL(10,7) NULL AFTER lokasi_presensi');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('ALTER TABLE admin_help_requests ADD COLUMN lng_pulang DECIMAL(10,7) NULL AFTER lat_pulang');
    } catch (PDOException $e) {
    }
    // Migration: add ekspresi_pulang for diff_location_checkout
    try {
        $pdo->exec('ALTER TABLE admin_help_requests ADD COLUMN ekspresi_pulang VARCHAR(50) NULL AFTER lng_pulang');
    } catch (PDOException $e) {
    }

    // Add is_read_by_user column for employee notifications
    try {
        $pdo->exec('ALTER TABLE admin_help_requests ADD COLUMN is_read_by_user BOOLEAN DEFAULT FALSE AFTER admin_note');
    } catch (PDOException $e) {
    }

    // Migration: add attendance_type and attendance_reason columns
    try {
        $pdo->exec("ALTER TABLE admin_help_requests ADD COLUMN attendance_type ENUM('wfo', 'wfa', 'overtime') DEFAULT 'wfo' AFTER request_type");
    } catch (PDOException $e) {
    } // Ignore if already exists
    try {
        $pdo->exec('ALTER TABLE admin_help_requests ADD COLUMN attendance_reason TEXT NULL AFTER attendance_type');
    } catch (PDOException $e) {
    } // Ignore if already exists

    // Attendance notes table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            type ENUM('izin','sakit') NOT NULL,
            keterangan TEXT NOT NULL,
            bukti LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            UNIQUE KEY unique_user_date (user_id, date),
            CONSTRAINT fk_an_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Monthly reports table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS monthly_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            year INT NOT NULL,
            month INT NOT NULL,
            summary TEXT NULL,
            achievements JSON NULL,
            obstacles JSON NULL,
            status ENUM('draft','belum di approve','approved','disapproved') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_user_month (user_id, year, month),
            CONSTRAINT fk_mr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Update existing monthly_reports table to use new ENUM values
    try {
        $pdo->exec("ALTER TABLE monthly_reports MODIFY COLUMN status ENUM('draft','belum di approve','approved','disapproved') DEFAULT 'draft'");
        // Update any existing 'submitted' status to 'belum di approve'
        $pdo->exec("UPDATE monthly_reports SET status = 'belum di approve' WHERE status = 'submitted'");
    } catch (PDOException $e) {
        // Ignore if column doesn't exist or already updated
        error_log('Monthly reports table update: '.$e->getMessage());
    }
    // Optimize performance: Add indexes for date fields if they do not exist
    try {
        $pdo->exec('CREATE INDEX idx_attendance_jam_masuk_iso ON attendance (jam_masuk_iso)');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('CREATE INDEX idx_attendance_notes_date ON attendance_notes (date)');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('CREATE INDEX idx_daily_reports_report_date ON daily_reports (report_date)');
    } catch (PDOException $e) {
    }

    // Create KPI Monthly Cache table
    try {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS kpi_monthly_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                year INT NOT NULL,
                month INT NOT NULL,
                ontime_count INT NOT NULL DEFAULT 0,
                wfo_count INT NOT NULL DEFAULT 0,
                wfa_count INT NOT NULL DEFAULT 0,
                late_count INT NOT NULL DEFAULT 0,
                izin_sakit_count INT NOT NULL DEFAULT 0,
                alpha_count INT NOT NULL DEFAULT 0,
                overtime_count INT NOT NULL DEFAULT 0,
                missing_daily_reports_count INT NOT NULL DEFAULT 0,
                total_late_minutes INT NOT NULL DEFAULT 0,
                total_working_days INT NOT NULL DEFAULT 0,
                actual_working_days INT NOT NULL DEFAULT 0,
                kpi_points DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                days_with_data INT NOT NULL DEFAULT 0,
                wfa_dates TEXT NULL,
                izin_sakit_dates TEXT NULL,
                alpha_dates TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_year_month (user_id, year, month),
                CONSTRAINT fk_kmc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    } catch (PDOException $e) {
        error_log('Failed to create kpi_monthly_cache table: '.$e->getMessage());
    }

    // Add columns to kpi_monthly_cache if they don't exist (migration for existing databases)
    try {
        $pdo->exec('ALTER TABLE kpi_monthly_cache ADD COLUMN wfa_dates TEXT NULL AFTER days_with_data');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('ALTER TABLE kpi_monthly_cache ADD COLUMN izin_sakit_dates TEXT NULL AFTER wfa_dates');
    } catch (PDOException $e) {
    }
    try {
        $pdo->exec('ALTER TABLE kpi_monthly_cache ADD COLUMN alpha_dates TEXT NULL AFTER izin_sakit_dates');
    } catch (PDOException $e) {
    }

    // Create database triggers for automatic cache invalidation
    $triggers = [
        'tg_attendance_insert' => 'CREATE TRIGGER tg_attendance_insert AFTER INSERT ON attendance FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = NEW.user_id AND (NEW.jam_masuk_iso IS NOT NULL AND year = YEAR(NEW.jam_masuk_iso) AND month = MONTH(NEW.jam_masuk_iso))',
        'tg_attendance_update' => 'CREATE TRIGGER tg_attendance_update AFTER UPDATE ON attendance FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = OLD.user_id AND ((OLD.jam_masuk_iso IS NOT NULL AND year = YEAR(OLD.jam_masuk_iso) AND month = MONTH(OLD.jam_masuk_iso)) OR (NEW.jam_masuk_iso IS NOT NULL AND year = YEAR(NEW.jam_masuk_iso) AND month = MONTH(NEW.jam_masuk_iso)))',
        'tg_attendance_delete' => 'CREATE TRIGGER tg_attendance_delete AFTER DELETE ON attendance FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = OLD.user_id AND (OLD.jam_masuk_iso IS NOT NULL AND year = YEAR(OLD.jam_masuk_iso) AND month = MONTH(OLD.jam_masuk_iso))',

        'tg_notes_insert' => 'CREATE TRIGGER tg_notes_insert AFTER INSERT ON attendance_notes FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = NEW.user_id AND year = YEAR(NEW.date) AND month = MONTH(NEW.date)',
        'tg_notes_update' => 'CREATE TRIGGER tg_notes_update AFTER UPDATE ON attendance_notes FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = OLD.user_id AND ((year = YEAR(OLD.date) AND month = MONTH(OLD.date)) OR (year = YEAR(NEW.date) AND month = MONTH(NEW.date)))',
        'tg_notes_delete' => 'CREATE TRIGGER tg_notes_delete AFTER DELETE ON attendance_notes FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = OLD.user_id AND year = YEAR(OLD.date) AND month = MONTH(OLD.date)',

        'tg_reports_insert' => 'CREATE TRIGGER tg_reports_insert AFTER INSERT ON daily_reports FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = NEW.user_id AND year = YEAR(NEW.report_date) AND month = MONTH(NEW.report_date)',
        'tg_reports_update' => 'CREATE TRIGGER tg_reports_update AFTER UPDATE ON daily_reports FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = OLD.user_id AND ((year = YEAR(OLD.report_date) AND month = MONTH(OLD.report_date)) OR (year = YEAR(NEW.report_date) AND month = MONTH(NEW.report_date)))',
        'tg_reports_delete' => 'CREATE TRIGGER tg_reports_delete AFTER DELETE ON daily_reports FOR EACH ROW DELETE FROM kpi_monthly_cache WHERE user_id = OLD.user_id AND year = YEAR(OLD.report_date) AND month = MONTH(OLD.report_date)',
    ];

    foreach ($triggers as $name => $sql) {
        try {
            $pdo->exec("DROP TRIGGER IF EXISTS $name");
            $pdo->exec($sql);
        } catch (PDOException $e) {
            error_log("Failed to create trigger $name: ".$e->getMessage());
        }
    }

    // Intern groups table — untuk pengelompokan pegawai per periode magang
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
    } catch (PDOException $e) {
        error_log('Failed to create intern_groups table: '.$e->getMessage());
    }

    // Intern group members table — pivot many-to-many antara kelompok dan pegawai
    try {
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
    } catch (PDOException $e) {
        error_log('Failed to create intern_group_members table: '.$e->getMessage());
    }
}

function verifyAttendanceTable(PDO $pdo): bool
{
    try {
        // Check if attendance table exists and has required columns
        $stmt = $pdo->query('DESCRIBE attendance');
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $requiredColumns = ['id', 'user_id', 'jam_masuk', 'jam_masuk_iso', 'ekspresi_masuk', 'foto_masuk', 'jam_pulang', 'jam_pulang_iso', 'ekspresi_pulang', 'foto_pulang', 'status', 'ket'];
        $missingColumns = array_diff($requiredColumns, $columns);

        if (! empty($missingColumns)) {
            error_log('Missing columns in attendance table: '.implode(', ', $missingColumns));

            return false;
        }

        return true;
    } catch (PDOException $e) {
        error_log('Error verifying attendance table: '.$e->getMessage());

        return false;
    }
}

function seedAdmin(PDO $pdo, string $email, string $password): void
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role='admin' LIMIT 1");
    $stmt->execute();
    $existing = $stmt->fetch();
    if (! $existing) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (role, email, nim, nama, prodi, startup, foto_base64, password_hash) VALUES ('admin', :email, NULL, 'Administrator', NULL, NULL, NULL, :hash)");
        $stmt->execute([':email' => $email, ':hash' => $hash]);
    }
}

function seedDefaultSettings(PDO $pdo): void
{
    $defaultSettings = [
        ['max_ontime_hour', '08', 'Jam maksimal untuk dianggap ontime (format 24 jam)'],
        ['min_checkin_hour', '04:00', 'Jam minimal dibukanya presensi masuk (format HH:MM)'],
        ['min_checkout_hour', '17', 'Jam minimal untuk bisa presensi pulang (format 24 jam)'],
        ['wfo_address', 'Fakultas Ilmu Terapan, Jl. Telekomunikasi, Bandung', 'Nama alamat pusat WFO (akan di-geocode)'],
        ['wfo_lat', '-6.9738', 'Latitude pusat WFO'],
        ['wfo_lng', '107.6300', 'Longitude pusat WFO'],
        ['wfo_radius_m', '1200', 'Radius wilayah WFO dalam meter'],
        // WFO detection via IP API settings
        ['wfo_mode', 'api', 'Mode deteksi WFO: api atau coordinate'],
        ['wfo_api_provider', 'ipinfo', 'Provider IP API: ipinfo | ipapi | ip-api'],
        ['wfo_api_token', '', 'Token API (opsional tergantung provider)'],
        ['wfo_api_org_keywords', 'Telkom University, Yayasan Pendidikan Telkom, Telkom University Bandung', 'Daftar kata kunci organisasi yang dianggap WFO (dipisah koma)'],
        ['wfo_api_asn_list', '', 'Daftar ASN yang dianggap WFO (contoh: AS7713), dipisah koma'],
        ['wfo_api_cidr_list', '', 'Daftar CIDR yang dianggap WFO (contoh: 103.23.44.0/22), dipisah koma'],
        ['wfo_wifi_ssids', 'Telkom University,TelU,WiFi Telkom University,WiFi-TelU,Telkom-University,TelU-Connect,TelU-Guest', 'Daftar SSID WiFi yang valid untuk WFO (dipisah koma)'],
        ['wfo_require_wifi', '1', 'Wajib menggunakan WiFi Telkom University untuk presensi WFO (1=Ya, 0=Tidak)'],
        ['attendance_period_end', date('Y-12-31'), 'Tanggal akhir periode perhitungan absen (YYYY-MM-DD)'],
        ['kpi_late_penalty_per_minute', '1', 'Pengurangan KPI per menit terlambat (%)'],
        ['kpi_late_max_deduction', '100', 'Maksimal pengurangan KPI karena terlambat per hari (%, default 100 = tidak terbatas)'],
        ['kpi_late_tolerance_minutes', '0', 'Toleransi keterlambatan dalam menit (0 = tidak ada toleransi)'],
        ['kpi_izin_sakit_score', '85', 'Nilai KPI untuk izin/sakit (%)'],
        ['kpi_alpha_score', '0', 'Nilai KPI untuk alpha (%)'],
        ['kpi_overtime_bonus', '5', 'Bonus KPI untuk overtime (%)'],
        ['max_daily_report_days_back', '5', 'Maksimal hari kebelakang untuk isi laporan harian (default: 5)'],
        ['max_monthly_report_months_back', '999', 'Maksimal bulan kebelakang untuk isi laporan bulanan (default: 999 = tidak terbatas)'],
        ['monthly_report_end_year', '2026', 'Tahun akhir untuk laporan bulanan (default: 2026)'],
        ['face_recognition_threshold', '0.38', 'Threshold untuk face recognition (0.0-1.0, semakin rendah semakin ketat, default: 0.38)'],
        ['face_recognition_input_size', '416', 'Ukuran input untuk face detection (semakin besar semakin akurat tapi lebih lambat, default: 416)'],
        ['face_recognition_score_threshold', '0.35', 'Score threshold untuk face detection (0.0-1.0, default: 0.35)'],
        ['face_recognition_quality_threshold', '0.55', 'Quality threshold untuk validasi wajah (0.0-1.0, default: 0.55)'],
        ['geocode_timeout', '3', 'Timeout untuk reverse geocoding dalam detik (default: 3)'],
        ['geocode_accuracy_radius', '50', 'Radius akurasi GPS dalam meter untuk validasi lokasi (default: 50)'],
    ];

    foreach ($defaultSettings as $setting) {
        $stmt = $pdo->prepare('SELECT id FROM settings WHERE setting_key = :key LIMIT 1');
        $stmt->execute([':key' => $setting[0]]);
        $existing = $stmt->fetch();

        if (! $existing) {
            $stmt = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, description) VALUES (:key, :value, :desc)');
            $stmt->execute([':key' => $setting[0], ':value' => $setting[1], ':desc' => $setting[2]]);
        }
    }
}

// === Modular includes (extracted from this file) ===
require_once __DIR__.'/geocoding.php';
require_once __DIR__.'/time_utils.php';
require_once __DIR__.'/wfo_detection.php';
require_once __DIR__.'/settings_helper.php';
require_once __DIR__.'/backup_trigger.php';
require_once __DIR__.'/auth_helper.php';
require_once __DIR__.'/image_helper.php';
require_once __DIR__.'/two_factor.php';
require_once __DIR__.'/face_functions.php';
require_once __DIR__.'/kpi.php';
require_once __DIR__.'/holiday.php';
require_once __DIR__.'/work_schedule.php';
require_once __DIR__.'/kpi_report.php';
