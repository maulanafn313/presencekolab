<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('attendance')->selectRaw('user_id, DATE(jam_masuk_iso) AS day')
            ->whereNotNull('jam_masuk_iso')->groupByRaw('user_id, DATE(jam_masuk_iso)')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Duplikasi absensi harian ditemukan. Audit dan koreksi sebelum menerapkan constraint.');
        }
        if (! Schema::hasColumn('attendance', 'attendance_day')) {
            // Virtual column avoids rewriting imported historical timestamps during DDL.
            Schema::table('attendance', fn (Blueprint $table) => $table->date('attendance_day')->virtualAs('DATE(jam_masuk_iso)'));
        }
        if (! Schema::hasIndex('attendance', 'attendance_user_day_unique')) {
            Schema::table('attendance', fn (Blueprint $table) => $table->unique(['user_id', 'attendance_day'], 'attendance_user_day_unique'));
        }
        if (! Schema::hasTable('attendance_corrections')) {
            Schema::create('attendance_corrections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('attendance_id')->index();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('reason');
                $table->json('before');
                $table->json('after');
                $table->timestamp('created_at');
            });
        }
    }

    public function down(): void
    {
        // Audit records must not be discarded by an application rollback.
        if (Schema::hasIndex('attendance', 'attendance_user_day_unique')) {
            Schema::table('attendance', fn (Blueprint $table) => $table->dropUnique('attendance_user_day_unique'));
        }
        if (Schema::hasColumn('attendance', 'attendance_day')) {
            Schema::table('attendance', fn (Blueprint $table) => $table->dropColumn('attendance_day'));
        }
    }
};
