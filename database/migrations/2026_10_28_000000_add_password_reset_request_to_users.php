<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lupa password: user mengisi password baru sendiri (disimpan sebagai hash di pending_password),
// admin menyetujui di Kelola Member, baru setelah itu pending_password dipindah ke password.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pending_password')) {
                $table->string('pending_password')->nullable();
            }
            if (! Schema::hasColumn('users', 'reset_requested_at')) {
                $table->dateTime('reset_requested_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_password', 'reset_requested_at']);
        });
    }
};
