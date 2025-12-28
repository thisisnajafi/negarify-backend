<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->index();
            $table->foreignId('token_bundle_id')->constrained()->onDelete('cascade');
            $table->integer('amount_tokens');
            $table->decimal('price_toman', 15, 2); // Calculated price in Toman
            $table->decimal('price_usd', 10, 2); // Base price in USD
            $table->decimal('dollar_rate', 15, 2); // USD to Toman rate at purchase time
            $table->string('zarinpal_authority')->nullable()->unique()->index();
            $table->string('zarinpal_ref_id')->nullable()->unique();
            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled'])->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
