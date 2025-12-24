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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('gallery_post_id')->constrained()->onDelete('cascade');
            $table->text('body');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->integer('likes_count')->default(0);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            
            $table->index(['gallery_post_id', 'parent_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
