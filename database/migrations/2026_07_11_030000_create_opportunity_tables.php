<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('posted_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type', 40)->index();
            $table->text('description');
            $table->string('country', 2)->nullable()->index();
            $table->string('province')->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->boolean('is_remote')->default(false)->index();
            $table->unsignedTinyInteger('minimum_age')->nullable();
            $table->unsignedTinyInteger('maximum_age')->nullable();
            $table->json('requirements')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('deadline')->nullable()->index();
            $table->unsignedInteger('applications_count')->default(0);
            $table->timestamps();
            $table->index(['status', 'deadline', 'published_at']);
            $table->index(['sport_id', 'position_id', 'status']);
            $table->index(['posted_by_id', 'status', 'created_at']);
        });

        Schema::create('opportunity_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('cover_letter')->nullable();
            $table->string('status', 24)->default('submitted')->index();
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['opportunity_id', 'user_id']);
            $table->index(['opportunity_id', 'status', 'created_at']);
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('saved_opportunities', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['opportunity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_opportunities');
        Schema::dropIfExists('opportunity_applications');
        Schema::dropIfExists('opportunities');
    }
};
