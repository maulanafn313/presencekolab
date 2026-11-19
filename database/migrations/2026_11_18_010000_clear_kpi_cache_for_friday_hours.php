<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Menit terlambat hari Jumat kini dihitung dari 07:30. Cache dibangun ulang otomatis.
    public function up(): void
    {
        if (Schema::hasTable('kpi_monthly_cache')) {
            DB::table('kpi_monthly_cache')->delete();
        }
    }

    public function down(): void
    {
        // Cache tidak perlu dipulihkan.
    }
};
