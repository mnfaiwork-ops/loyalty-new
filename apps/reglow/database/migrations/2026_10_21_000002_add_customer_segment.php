<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kontrol isi kado per jenis pelanggan (baru/lama). Kedua app berbagi satu DB: idempoten.
return new class extends Migration
{
    public function up(): void
    {
        // Untuk siapa hadiah ini: semua, baru, lama. Kosong = bawaan (hadiah utama: lama).
        if (! Schema::hasColumn('prize_catalog', 'segment')) {
            Schema::table('prize_catalog', function (Blueprint $table) {
                $table->string('segment', 10)->nullable();
            });
        }

        // Jenis pelanggan saat sesi dibuat, supaya bobot dan hadiah satu sesi tidak berubah di tengah main.
        if (! Schema::hasColumn('game_sessions', 'segment')) {
            Schema::table('game_sessions', function (Blueprint $table) {
                $table->string('segment', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['prize_catalog', 'game_sessions'] as $table) {
            if (Schema::hasColumn($table, 'segment')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('segment'));
            }
        }
    }
};
