<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 1: both brand apps share one DB, so this must be idempotent.
// Menyimpan hitungan percobaan masuk game (AWB+HP) yang GAGAL, untuk kunci
// akses 15 menit setelah 5x salah (spec §7). HP disimpan sebagai hash, bukan
// mentah, supaya tidak ada data pribadi telanjang di tabel rate-limit.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('play_attempts')) {
            Schema::create('play_attempts', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->string('key'); // hash(brand|awb|phone)
                $table->unsignedSmallInteger('fails')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamp('last_fail_at')->nullable();
                $table->timestamps();
                $table->unique(['brand', 'key']);
                $table->index(['brand', 'locked_until']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('play_attempts');
    }
};
