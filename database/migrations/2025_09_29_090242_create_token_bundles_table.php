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
        Schema::create('token_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Starter Pack", "Standard Pack", etc.
            $table->integer('token_amount'); // 100, 500, 1000, 2000
            $table->decimal('price_usd', 10, 2); // Base price in USD
            $table->integer('bonus_tokens')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('display_order')->default(0);
            $table->timestamps();
            
            $table->index(['is_active', 'display_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_bundles');
    }
};
