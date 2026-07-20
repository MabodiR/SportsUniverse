<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->index();
            $table->string('league', 120)->nullable()->index();
            $table->string('team', 120)->nullable()->index();
            $table->string('competition', 160)->nullable();
            $table->string('content_type', 60)->nullable()->index();
            $table->string('language', 12)->nullable();
            $table->json('skill_tags')->nullable();
            $table->text('transcript')->nullable();
            $table->text('detected_text')->nullable();
            $table->json('content_labels')->nullable();
            $table->json('content_embedding')->nullable();
            $table->timestamp('analyzed_at')->nullable();
        });

        Schema::create('video_content_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 40);
            $table->string('value', 160);
            $table->decimal('weight', 8, 4)->default(1);
            $table->string('source', 24)->default('metadata');
            $table->timestamps();
            $table->unique(['video_id', 'dimension', 'value']);
            $table->index(['dimension', 'value', 'video_id']);
        });

        Schema::create('user_content_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dimension', 40);
            $table->string('value', 160);
            $table->decimal('score', 10, 4)->default(0);
            $table->unsignedInteger('signals_count')->default(0);
            $table->timestamp('last_signaled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'dimension', 'value']);
            $table->index(['user_id', 'score']);
        });

        Schema::create('feed_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('event', 32);
            $table->decimal('weight', 8, 4);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['video_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_interactions');
        Schema::dropIfExists('user_content_preferences');
        Schema::dropIfExists('video_content_topics');
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['country_code', 'league', 'team', 'competition', 'content_type', 'language', 'skill_tags', 'transcript', 'detected_text', 'content_labels', 'content_embedding', 'analyzed_at']);
        });
    }
};
