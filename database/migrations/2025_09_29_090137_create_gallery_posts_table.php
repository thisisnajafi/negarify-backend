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
        Schema::create('gallery_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->index();
            $table->foreignId('generation_job_id')->constrained()->onDelete('cascade')->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('tags_json')->nullable(); // Array of tags
            $table->enum('visibility', ['public', 'private'])->default('public')->index();
            $table->boolean('is_curated')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->integer('likes_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->boolean('prompt_visible')->default(true);
            $table->boolean('model_visible')->default(true);
            $table->timestamp('curated_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'visibility']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_posts');
    }
};
