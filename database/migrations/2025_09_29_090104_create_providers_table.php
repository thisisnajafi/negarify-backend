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
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_base_url');
            $table->text('api_key_encrypted');
            $table->decimal('cost_per_image_usd_override', 8, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable(); // Additional provider-specific config
            $table->integer('priority')->default(0); // Provider selection priority
            $table->timestamps();
            
            $table->index(['enabled', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
