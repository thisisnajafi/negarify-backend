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
        Schema::create('image_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('model_id')->constrained()->onDelete('cascade');
            $table->text('prompt');
            $table->json('params_json'); // size, quality, variations, etc.
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->integer('tokens_consumed')->default(0);
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->string('result_url')->nullable();
            $table->text('error_message')->nullable();
            $table->string('job_id')->unique(); // External job ID from provider
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['provider_id', 'status']);
            $table->index('job_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_jobs');
    }
};
