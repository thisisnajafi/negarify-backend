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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_from')->default('USD');
            $table->string('currency_to')->default('IRR');
            $table->decimal('rate', 15, 2); // USD to Rials (will be divided by 10 for Toman)
            $table->string('source')->default('tgju');
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};

