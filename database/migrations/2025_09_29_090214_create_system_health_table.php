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
        Schema::create('system_health', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name')->index();
            $table->decimal('metric_value', 15, 4);
            $table->string('metric_unit')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('recorded_at')->index();
            $table->timestamp('created_at');
            
            $table->index(['metric_name', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_health');
    }
};
