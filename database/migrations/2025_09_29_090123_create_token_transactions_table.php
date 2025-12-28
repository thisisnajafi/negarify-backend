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
        Schema::create('token_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->index();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('generation_job_id')->nullable()->constrained()->onDelete('set null');
            $table->integer('amount_tokens'); // Positive for purchase, negative for consumption
            $table->decimal('amount_usd', 10, 4)->nullable();
            $table->enum('type', ['purchase', 'consume', 'refund', 'bonus', 'adjustment'])->index();
            $table->string('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_transactions');
    }
};
