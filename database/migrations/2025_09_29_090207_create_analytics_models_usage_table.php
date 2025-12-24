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
            $table->foreignId('model_id')->constrained()->onDelete('cascade');
            $table->integer('requests_count')->default(0);
            $table->integer('tokens_consumed')->default(0);
            $table->decimal('cost_usd', 10, 4)->default(0);
            $table->decimal('avg_latency_ms', 8, 2)->default(0);
            $table->integer('failures_count')->default(0);
            $table->date('month'); // YYYY-MM-01 format
            $table->timestamps();
            
            $table->unique(['model_id', 'month']);
            $table->index('month');
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
