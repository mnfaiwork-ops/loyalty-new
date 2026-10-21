<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kuota bersama hadiah utama, mis. lima jenis Philips berbagi satu kuota "Philips". Idempoten.
return new class extends Migration
{
    public function up(): void
    {
        // Nama baris quota_caps yang dipakai hadiah ini. Kosong = kuota atas nama hadiah sendiri.
        if (! Schema::hasColumn('prize_catalog', 'quota_name')) {
            Schema::table('prize_catalog', function (Blueprint $table) {
                $table->string('quota_name', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prize_catalog', 'quota_name')) {
            Schema::table('prize_catalog', fn (Blueprint $t) => $t->dropColumn('quota_name'));
        }
    }
};
