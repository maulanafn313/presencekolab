<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('intern_groups')) {
            Schema::create('intern_groups', function (Blueprint $table) {
                $table->id();
                $table->string('nama', 255);
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->boolean('is_archived')->default(false);
                $table->dateTime('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('intern_group_members')) {
            Schema::create('intern_group_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamp('created_at')->useCurrent();
                
                $table->unique(['group_id', 'user_id'], 'unique_group_user');
                $table->foreign('group_id', 'fk_igm_group')->references('id')->on('intern_groups')->onDelete('cascade');
                $table->foreign('user_id', 'fk_igm_user')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('kpi_monthly_cache')) {
            Schema::create('kpi_monthly_cache', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->integer('year');
                $table->integer('month');
                $table->integer('ontime_count')->default(0);
                $table->integer('wfo_count')->default(0);
                $table->integer('wfa_count')->default(0);
                $table->integer('late_count')->default(0);
                $table->integer('izin_sakit_count')->default(0);
                $table->integer('alpha_count')->default(0);
                $table->integer('overtime_count')->default(0);
                $table->integer('missing_daily_reports_count')->default(0);
                $table->integer('total_late_minutes')->default(0);
                $table->integer('total_working_days')->default(0);
                $table->integer('actual_working_days')->default(0);
                $table->decimal('kpi_points', 10, 2)->default(0.00);
                $table->integer('days_with_data')->default(0);
                $table->text('wfa_dates')->nullable();
                $table->text('izin_sakit_dates')->nullable();
                $table->text('alpha_dates')->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                
                $table->unique(['user_id', 'year', 'month'], 'uniq_user_year_month');
                $table->foreign('user_id', 'fk_kmc_user')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_tables');
    }
};
