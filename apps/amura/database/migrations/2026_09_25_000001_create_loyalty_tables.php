<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phase 1: both brand apps share the same database (brand isolation is enforced
// by the `brand` column + BrandScope, not by separate DBs), so this migration
// runs twice against one DB. All statements are therefore idempotent.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('cs')->after('password');
                $table->string('brand_scope')->after('role');
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('phone');
                $table->string('name')->nullable();
                $table->string('brand');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->unique(['brand', 'phone']);
            });
        }

        if (! Schema::hasTable('prize_catalog')) {
            Schema::create('prize_catalog', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->string('name');
                $table->string('tier');
                $table->integer('point_value')->nullable();
                $table->integer('face_value')->nullable();
                $table->integer('quota_total')->nullable();
                $table->integer('quota_used')->default(0);
                $table->boolean('is_capped')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->index('brand');
            });
        }

        if (! Schema::hasTable('game_sessions')) {
            Schema::create('game_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('customers');
                $table->string('brand');
                $table->string('session_token')->unique();
                $table->smallInteger('chance_total')->default(2);
                $table->smallInteger('chance_used')->default(0);
                $table->jsonb('tier_config');
                $table->string('awb');
                $table->string('gate_source')->default('stub');
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->unique(['customer_id', 'awb']);
                $table->index(['brand', 'customer_id']);
            });
        }

        if (! Schema::hasTable('game_prizes')) {
            Schema::create('game_prizes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
                $table->smallInteger('slot');
                $table->string('tier');
                $table->foreignId('prize_catalog_id')->nullable()->constrained('prize_catalog');
                $table->boolean('picked')->default(false);
                $table->boolean('opened')->default(false);
                $table->boolean('taken')->default(false);
                $table->timestamps();
                $table->unique(['game_session_id', 'slot']);
            });
        }

        if (! Schema::hasTable('claims')) {
            Schema::create('claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_session_id')->constrained('game_sessions');
                $table->foreignId('game_prize_id')->constrained('game_prizes');
                $table->string('claim_code')->unique();
                $table->string('status')->default('menunggu');
                $table->foreignId('handled_by')->nullable()->constrained('users');
                $table->foreignId('finance_by')->nullable()->constrained('users');
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();
                $table->index('game_session_id');
                $table->index('game_prize_id');
            });
        }

        if (! Schema::hasTable('quota_caps')) {
            Schema::create('quota_caps', function (Blueprint $table) {
                $table->id();
                $table->string('brand');
                $table->string('prize_name');
                $table->integer('cap_units');
                $table->integer('used_units')->default(0);
                $table->date('program_start');
                $table->date('program_end');
                $table->timestamps();
                $table->index('brand');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_caps');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('game_prizes');
        Schema::dropIfExists('game_sessions');
        Schema::dropIfExists('prize_catalog');
        Schema::dropIfExists('customers');

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['role', 'brand_scope']);
            });
        }
    }
};
