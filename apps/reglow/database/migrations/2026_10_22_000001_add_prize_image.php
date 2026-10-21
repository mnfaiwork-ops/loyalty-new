<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Foto hadiah Gatcha untuk gambar share story, bisa diganti Game Admin. Idempoten (DB bersama).
return new class extends Migration
{
    public function up(): void
    {
        // Path di disk public. Kosong = foto bawaan public/img/hadiah/<brand>/<slug nama>.webp kalau ada.
        if (! Schema::hasColumn('prize_catalog', 'image')) {
            Schema::table('prize_catalog', function (Blueprint $table) {
                $table->string('image')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prize_catalog', 'image')) {
            Schema::table('prize_catalog', fn (Blueprint $t) => $t->dropColumn('image'));
        }
    }
};
