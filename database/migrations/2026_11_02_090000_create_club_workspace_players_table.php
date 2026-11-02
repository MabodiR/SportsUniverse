<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_workspace_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('club_workspaces')->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('active');
            $table->string('squad', 80)->nullable();
            $table->string('shirt_number', 12)->nullable();
            $table->date('joined_on')->nullable();
            $table->date('contract_ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'athlete_id']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_workspace_players');
    }
};
