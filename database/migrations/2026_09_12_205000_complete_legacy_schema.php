<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('intern_groups')) {
            Schema::create('intern_groups', function (Blueprint $table) {
                $table->id();
                $table->string('nama');
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->boolean('is_archived')->default(false);
                $table->dateTime('archived_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('intern_group_members')) {
            Schema::create('intern_group_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['group_id', 'user_id']);
                $table->index('user_id');
            });
        }
        if (! Schema::hasTable('kpi_monthly_cache')) {
            Schema::create('kpi_monthly_cache', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->integer('year');
                $table->integer('month');
                foreach (['ontime_count', 'wfo_count', 'wfa_count', 'late_count', 'izin_sakit_count', 'alpha_count', 'overtime_count', 'missing_daily_reports_count', 'total_late_minutes', 'total_working_days', 'actual_working_days', 'days_with_data'] as $column) {
                    $table->integer($column)->default(0);
                }
                $table->decimal('kpi_points', 10, 2)->default(0);
                foreach (['wfa_dates', 'izin_sakit_dates', 'alpha_dates'] as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamp('updated_at')->useCurrent();
                $table->unique(['user_id', 'year', 'month']);
            });
        }
        $additions = [
            'users' => ['face_embedding_128' => 'text', 'advanced_features' => 'longText', 'facial_geometry' => 'longText', 'feature_vector' => 'longText'],
            'attendance' => ['screenshot_masuk' => 'longText', 'screenshot_pulang' => 'longText', 'face_thumbnail_masuk' => 'mediumText', 'face_thumbnail_pulang' => 'mediumText', 'alasan_lokasi_berbeda' => 'text'],
            'admin_help_requests' => ['bukti_presensi' => 'longText', 'lokasi_presensi' => 'text', 'lat_pulang' => 'double', 'lng_pulang' => 'double', 'ekspresi_pulang' => 'string', 'alasan_izin' => 'text', 'jenis_izin' => 'string', 'bukti_izin' => 'longText', 'bug_description' => 'text', 'bug_proof' => 'longText'],
        ];
        foreach ($additions as $name => $columns) {
            foreach ($columns as $column => $type) {
                if (! Schema::hasColumn($name, $column)) {
                    Schema::table($name, fn (Blueprint $table) => $table->{$type}($column)->nullable());
                }
            }
        }
        // Widen legacy enums to include states already used by existing code.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE admin_help_requests MODIFY request_type VARCHAR(100) NOT NULL');
            DB::statement("ALTER TABLE monthly_reports MODIFY status VARCHAR(50) NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        // Additive reconciliation is intentionally non-destructive on imported databases.
        // Restoring a previous schema requires the verified pre-deployment backup.
    }
};
