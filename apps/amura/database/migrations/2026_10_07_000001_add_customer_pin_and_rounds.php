<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PIN pelanggan dan papan baru per kesempatan (spec 2026-10-07).
// Kedua app berbagi satu database, jadi setiap langkah idempoten.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'pin_hash')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('pin_hash')->nullable();
                // Naik setiap PIN diganti, supaya perangkat yang diingat dengan PIN lama ikut keluar.
                $table->unsignedInteger('pin_version')->default(0);
                $table->boolean('pin_must_change')->default(false);
                $table->timestamp('pin_set_at')->nullable();
                $table->foreignId('pin_reset_by')->nullable()->constrained('users');
                $table->timestamp('pin_reset_at')->nullable();
            });
        }

        // Kesempatan ke-n memakai 24 kado barunya sendiri, nomor slot yang sama.
        if (! Schema::hasColumn('game_prizes', 'round')) {
            Schema::table('game_prizes', function (Blueprint $table) {
                $table->smallInteger('round')->default(1);
                $table->dropUnique(['game_session_id', 'slot']);
                $table->unique(['game_session_id', 'round', 'slot']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('game_prizes', 'round')) {
            Schema::table('game_prizes', function (Blueprint $table) {
                $table->dropUnique(['game_session_id', 'round', 'slot']);
            });
            // Papan kesempatan 2 dst. tidak muat di skema lama yang satu papan per sesi.
            \Illuminate\Support\Facades\DB::table('game_prizes')->where('round', '>', 1)
                ->whereNotExists(fn ($q) => $q->from('claims')->whereColumn('claims.game_prize_id', 'game_prizes.id'))
                ->whereNotExists(fn ($q) => $q->from('point_lots')->whereColumn('point_lots.game_prize_id', 'game_prizes.id'))
                ->delete();
            Schema::table('game_prizes', function (Blueprint $table) {
                $table->dropColumn('round');
            });
        }

        if (Schema::hasColumn('customers', 'pin_hash')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pin_reset_by');
                $table->dropColumn(['pin_hash', 'pin_version', 'pin_must_change', 'pin_set_at', 'pin_reset_at']);
            });
        }
    }
};
