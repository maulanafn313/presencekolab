<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_submissions')) {
            Schema::create('attendance_submissions', function (Blueprint $table) {
                $table->id();
                // Legacy imports use signed INT; new installs use BIGINT IDs.
                $table->unsignedBigInteger('user_id');
                $table->string('request_key', 100);
                $table->string('request_hash', 64);
                $table->text('response');
                $table->unsignedSmallInteger('status');
                $table->timestamp('created_at');
                $table->unique(['user_id', 'request_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_submissions');
    }
};
