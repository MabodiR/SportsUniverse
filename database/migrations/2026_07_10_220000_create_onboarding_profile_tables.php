<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('date_of_birth')->nullable()->index();
            $table->string('gender', 32)->nullable()->index();
            $table->text('bio')->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->string('province')->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('locality')->nullable();
            $table->string('township')->nullable();
            $table->string('profile_image_path')->nullable();
            $table->unsignedTinyInteger('completeness')->default(20)->index();
            $table->timestamps();
        });

        Schema::create('athlete_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_sport')->nullable()->index();
            $table->string('position')->nullable()->index();
            $table->string('club_name')->nullable()->index();
            $table->string('playing_level', 40)->nullable()->index();
            $table->string('dominant_side', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('fan_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('interested_sports')->nullable();
            $table->text('favourites')->nullable();
            $table->json('notification_preferences')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fan_profiles');
        Schema::dropIfExists('athlete_profiles');
        Schema::dropIfExists('user_profiles');
    }
};
