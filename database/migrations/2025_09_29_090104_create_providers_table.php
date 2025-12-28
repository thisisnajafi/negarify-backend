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
            $table->string('name'); // "Segmind"
            $table->string('api_base_url'); // "https://api.segmind.com"
            $table->text('api_key_encrypted'); // Encrypted using Laravel encryption
            $table->decimal('cost_per_image_usd', 10, 4)->nullable();
            $table->decimal('cost_per_video_usd', 10, 4)->nullable();
            $table->decimal('cost_per_audio_usd', 10, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
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
