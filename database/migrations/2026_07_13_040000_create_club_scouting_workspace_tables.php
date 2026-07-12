<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_workspaces', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('bio')->nullable();
            $t->string('website')->nullable();
            $t->timestamps();
        });
        Schema::create('club_staff', function (Blueprint $t) {
            $t->foreignId('workspace_id')->constrained('club_workspaces')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('role', 30)->default('scout');
            $t->string('status', 20)->default('active');
            $t->timestamps();
            $t->primary(['workspace_id', 'user_id']);
        });
        Schema::create('talent_shortlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained('club_workspaces')->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('shortlist_athletes', function (Blueprint $t) {
            $t->foreignId('shortlist_id')->constrained('talent_shortlists')->cascadeOnDelete();
            $t->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('added_by_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();
            $t->primary(['shortlist_id', 'athlete_id']);
        });
        Schema::create('scouting_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained('club_workspaces')->cascadeOnDelete();
            $t->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $t->text('note');
            $t->unsignedTinyInteger('rating')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'athlete_id']);
        });
        Schema::create('trial_invitations', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('workspace_id')->constrained('club_workspaces')->cascadeOnDelete();
            $t->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('sent_by_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $t->string('title');
            $t->text('message');
            $t->string('status', 20)->default('sent');
            $t->timestamp('sent_at');
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_invitations');
        Schema::dropIfExists('scouting_notes');
        Schema::dropIfExists('shortlist_athletes');
        Schema::dropIfExists('talent_shortlists');
        Schema::dropIfExists('club_staff');
        Schema::dropIfExists('club_workspaces');
    }
};
