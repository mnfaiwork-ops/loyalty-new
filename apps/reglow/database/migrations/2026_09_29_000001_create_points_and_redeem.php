<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Saldo poin, katalog bulanan, dan alur redeem (spec 2026-09-29).
// Kedua app berbagi satu database, jadi setiap langkah idempoten.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'brand')) {
            Schema::table('claims', function (Blueprint $table) {
                $table->string('brand')->nullable()->index();
                $table->foreignId('customer_id')->nullable()->constrained('customers');
                $table->string('source')->default('gatcha');
                $table->string('prize_name')->nullable();
                $table->unsignedBigInteger('reward_catalog_item_id')->nullable();
                $table->integer('points_spent')->nullable();
                $table->timestamp('redeem_requested_at')->nullable();
                $table->string('shipping_awb')->nullable();
                $table->string('rejected_reason')->nullable();
            });

            Schema::table('claims', function (Blueprint $table) {
                $table->unsignedBigInteger('game_session_id')->nullable()->change();
                $table->unsignedBigInteger('game_prize_id')->nullable()->change();
                $table->string('status')->default('belum_redeem')->change();
            });

            DB::statement(<<<'SQL'
                update claims set brand = gs.brand, customer_id = gs.customer_id
                from game_sessions gs
                where claims.game_session_id = gs.id
            SQL);

            DB::statement(<<<'SQL'
                update claims set prize_name = coalesce(pc.name, case gp.tier
                    when 'hadiah_besar' then 'Hadiah Utama'
                    when 'voucher' then 'Voucher'
                    when 'merch' then 'Merch'
                    else 'Poin' end)
                from game_prizes gp
                left join prize_catalog pc on pc.id = gp.prize_catalog_id
                where claims.game_prize_id = gp.id
            SQL);

            DB::table('claims')->where('status', 'menunggu')->update(['status' => 'belum_redeem']);
        }

        if (! Schema::hasTable('claim_events')) {
            Schema::create('claim_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
                $table->string('from_status')->nullable();
                $table->string('to_status');
                $table->string('actor');
                $table->foreignId('user_id')->nullable()->constrained('users');
                $table->string('note')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index('claim_id');
            });
        }

        if (! Schema::hasTable('point_lots')) {
            Schema::create('point_lots', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->foreignId('customer_id')->constrained('customers');
                $table->foreignId('game_prize_id')->nullable()->unique()->constrained('game_prizes');
                $table->integer('points');
                $table->integer('remaining');
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->index(['customer_id', 'expires_at']);
            });
        }

        if (! Schema::hasTable('point_movements')) {
            Schema::create('point_movements', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->foreignId('customer_id')->constrained('customers');
                $table->foreignId('point_lot_id')->constrained('point_lots');
                $table->foreignId('claim_id')->nullable()->constrained('claims');
                $table->string('type');
                $table->integer('points');
                $table->timestamp('created_at')->useCurrent();
                $table->index('customer_id');
                $table->index('claim_id');
            });
        }

        if (! Schema::hasTable('reward_catalogs')) {
            Schema::create('reward_catalogs', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->string('period', 7);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->unique(['brand', 'period']);
            });
        }

        if (! Schema::hasTable('reward_catalog_items')) {
            Schema::create('reward_catalog_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reward_catalog_id')->constrained('reward_catalogs')->cascadeOnDelete();
                $table->string('name');
                $table->integer('points_cost');
                $table->string('image_path')->nullable();
                $table->integer('stock')->nullable();
                $table->integer('stock_used')->default(0);
                $table->boolean('active')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_catalog_items');
        Schema::dropIfExists('reward_catalogs');
        Schema::dropIfExists('point_movements');
        Schema::dropIfExists('point_lots');
        Schema::dropIfExists('claim_events');

        if (Schema::hasColumn('claims', 'brand')) {
            DB::table('claims')->where('status', 'belum_redeem')->update(['status' => 'menunggu']);

            Schema::table('claims', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_id');
                $table->dropColumn(['brand', 'source', 'prize_name', 'reward_catalog_item_id', 'points_spent',
                    'redeem_requested_at', 'shipping_awb', 'rejected_reason']);
                $table->string('status')->default('menunggu')->change();
            });
        }
    }
};
