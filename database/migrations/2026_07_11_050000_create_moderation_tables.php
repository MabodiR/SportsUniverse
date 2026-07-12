<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->index();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 50)->index();
            $table->text('details')->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->constrained('users')->restrictOnDelete();
            $table->nullableMorphs('moderatable');
            $table->foreignId('report_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50)->index();
            $table->string('previous_status', 40)->nullable();
            $table->string('new_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['moderator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('reports');
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by_id');
            $table->dropColumn('verified_at');
        });
    }
};
