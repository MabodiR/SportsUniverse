<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('athlete_career_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('team_name');
            $table->string('role')->nullable();
            $table->string('level')->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'started_on']);
        });

        Schema::create('athlete_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('issuer')->nullable();
            $table->date('achieved_on')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'achieved_on']);
        });

        Schema::create('athlete_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('season', 40);
            $table->string('competition')->nullable();
            $table->string('name', 80);
            $table->decimal('value', 12, 2);
            $table->string('unit', 30)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'season']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_statistics');
        Schema::dropIfExists('athlete_achievements');
        Schema::dropIfExists('athlete_career_entries');
    }
};
