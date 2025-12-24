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
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('amount_tokens', 15, 2);
            $table->decimal('amount_usd', 8, 2)->nullable();
            $table->enum('type', ['purchase', 'consume', 'refund', 'bonus', 'adjustment']);
            $table->string('reference_id')->nullable(); // Order ID, job ID, etc.
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional transaction data
            $table->timestamps();
            
            $table->index(['user_id', 'type']);
            $table->index(['reference_id', 'type']);
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
