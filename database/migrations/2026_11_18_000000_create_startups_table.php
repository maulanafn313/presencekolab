<?php

use App\Services\StartupDirectory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Mencegah nama kembar seperti "Uni Inside" dan "Uni-Inside".
            $table->string('name_key', 100)->unique();
            $table->string('portal_id', 50)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Data mahasiswa tidak diubah di sini. Admin menyesuaikannya dari menu Kelola Startup.
        $now = now();
        foreach (['idSpora', 'JagoAI', 'JagoFarm', 'Kroombox', 'Ngolab', 'Non-Startup', 'Sorgumology', 'Sorgummi', 'Uni Inside'] as $name) {
            DB::table('startups')->insert(['name' => $name, 'name_key' => StartupDirectory::key($name), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('startups');
    }
};
