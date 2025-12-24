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
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->string('model_name');
            $table->string('quality_profile'); // HD, STD, LOW, etc.
            $table->decimal('base_cost_usd', 8, 4);
            $table->integer('default_tokens');
            $table->json('supported_sizes')->nullable(); // Available image sizes
            $table->json('supported_styles')->nullable(); // Available styles
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            
            $table->index(['provider_id', 'enabled']);
            $table->index('model_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('models');
    }
};
