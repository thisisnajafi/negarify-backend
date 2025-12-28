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
        Schema::create('analytics_models_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained()->onDelete('cascade')->index();
            $table->enum('job_type', ['image', 'video', 'audio'])->index();
            $table->integer('requests_count')->default(0);
            $table->integer('successful_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('tokens_consumed')->default(0);
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->decimal('revenue_usd', 10, 4)->default(0);
            $table->integer('avg_latency_ms')->nullable();
            $table->enum('period_type', ['daily', 'weekly', 'monthly'])->index();
            $table->date('period_start')->index();
            $table->date('period_end');
            $table->timestamps();
            
            $table->index(['model_id', 'period_type', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_models_usage');
    }
};
