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
            $table->foreignId('provider_id')->constrained()->onDelete('cascade')->index();
            $table->string('model_name'); // e.g., "flux-dev", "stable-diffusion-xl"
            $table->enum('model_type', ['image', 'video', 'audio'])->index();
            $table->string('api_endpoint'); // Segmind API endpoint path
            $table->string('quality_profile')->nullable(); // "hd", "standard", "budget"
            $table->decimal('base_cost_usd', 10, 4)->default(0);
            $table->integer('default_tokens'); // Tokens required per generation
            $table->boolean('supports_size')->default(true);
            $table->boolean('supports_style')->default(false);
            $table->string('max_resolution')->nullable(); // "2048x2048"
            $table->boolean('enabled')->default(true)->index();
            $table->timestamps();
            
            $table->index(['provider_id', 'enabled']);
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
