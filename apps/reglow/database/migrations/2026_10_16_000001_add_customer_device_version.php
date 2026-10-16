<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kedua app berbagi satu DB di fase 1, jadi migration ini bisa jalan dua kali: idempoten.
return new class extends Migration
{
    public function up(): void
    {
        // Versi perangkat yang diingat. Naik saat pelanggan keluar, jadi cookie
        // "perangkat diingat" yang tersalin ke tempat lain ikut tidak berlaku.
        if (! Schema::hasColumn('customers', 'device_version')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedInteger('device_version')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'device_version')) {
            Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('device_version'));
        }
    }
};
