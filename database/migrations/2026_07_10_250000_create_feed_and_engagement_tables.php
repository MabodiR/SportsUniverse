<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->unique()->constrained('media')->restrictOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('visibility', 24)->default('public')->index();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('shares_count')->default(0);
            $table->unsignedBigInteger('saves_count')->default(0);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->index(['status', 'visibility', 'published_at']);
            $table->index(['sport_id', 'status', 'published_at']);
            $table->index(['user_id', 'status', 'published_at']);
        });

        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_id', 'followed_id']);
            $table->index(['followed_id', 'created_at']);
        });

        Schema::create('video_likes', function (Blueprint $table) {
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['video_id', 'user_id']);
        });

        Schema::create('saved_videos', function (Blueprint $table) {
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['video_id', 'user_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('moderation_status', 24)->default('approved')->index();
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->timestamps();
            $table->index(['video_id', 'parent_id', 'created_at']);
        });

        Schema::create('video_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 40)->default('copy_link');
            $table->timestamps();
            $table->index(['video_id', 'created_at']);
        });

        Schema::create('video_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_key', 64)->nullable();
            $table->unsignedInteger('watched_ms')->default(0);
            $table->boolean('completed')->default(false);
            $table->date('viewed_on');
            $table->timestamps();
            $table->unique(['video_id', 'user_id', 'viewed_on']);
            $table->index(['video_id', 'viewed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_views');
        Schema::dropIfExists('video_shares');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('saved_videos');
        Schema::dropIfExists('video_likes');
        Schema::dropIfExists('follows');
        Schema::dropIfExists('videos');
    }
};
