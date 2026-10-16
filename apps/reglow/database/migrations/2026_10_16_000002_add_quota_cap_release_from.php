<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tanggal hadiah besar mulai boleh keluar. Kosong = 14 hari terakhir periode. */
    public function up(): void
    {
        Schema::table('quota_caps', function (Blueprint $table) {
            $table->date('release_from')->nullable()->after('program_start');
        });
    }

    public function down(): void
    {
        Schema::table('quota_caps', function (Blueprint $table) {
            $table->dropColumn('release_from');
        });
    }
};
