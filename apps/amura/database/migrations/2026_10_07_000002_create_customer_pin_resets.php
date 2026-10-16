<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Log setiap reset PIN oleh CS (desain 3l). customers.pin_reset_* hanya menyimpan
// reset terakhir. Kedua app berbagi satu database, jadi langkahnya idempoten.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_pin_resets')) {
            return;
        }

        Schema::create('customer_pin_resets', function (Blueprint $table) {
            $table->id();
            $table->string('brand', 20);
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('reset_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['brand', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_pin_resets');
    }
};
