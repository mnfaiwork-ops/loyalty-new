<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Riwayat import setting (Super Admin) + lokasi backup setting sebelum import. */
    public function up(): void
    {
        Schema::create('setting_imports', function (Blueprint $table) {
            $table->id();
            $table->string('brand')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('backup_path');
            $table->unsignedInteger('change_count');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_imports');
    }
};
