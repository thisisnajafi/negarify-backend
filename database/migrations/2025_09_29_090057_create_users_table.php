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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->decimal('tokens_balance', 15, 2)->default(0);
            $table->enum('role', ['user', 'admin', 'moderator'])->default('user');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index('phone');
            $table->index('email');
            $table->index('tokens_balance');
            $table->index('role');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
