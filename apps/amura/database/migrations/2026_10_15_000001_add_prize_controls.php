<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kedua app berbagi satu DB di fase 1, jadi migration ini bisa jalan dua kali: idempoten.
return new class extends Migration
{
    public function up(): void
    {
        // Stok bulanan hadiah voucher/merch Gatcha. Null = tanpa batas.
        if (! Schema::hasColumn('prize_catalog', 'monthly_stock')) {
            Schema::table('prize_catalog', function (Blueprint $table) {
                $table->integer('monthly_stock')->nullable();
            });
        }

        // Nilai produk order (setelah diskon) dari gate, dasar nilai kado poin.
        if (! Schema::hasColumn('game_sessions', 'order_amount')) {
            Schema::table('game_sessions', function (Blueprint $table) {
                $table->bigInteger('order_amount')->nullable();
            });
        }

        // Nilai kado poin dikunci saat kado dibuka, supaya angka yang tampil sama dengan yang masuk saldo.
        if (! Schema::hasColumn('game_prizes', 'points')) {
            Schema::table('game_prizes', function (Blueprint $table) {
                $table->integer('points')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['prize_catalog' => 'monthly_stock', 'game_sessions' => 'order_amount', 'game_prizes' => 'points'] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
