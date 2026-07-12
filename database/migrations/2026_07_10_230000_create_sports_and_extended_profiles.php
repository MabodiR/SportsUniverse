<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['sport_id', 'slug']);
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('user_id');
            $table->string('cover_image_path')->nullable()->after('profile_image_path');
            $table->boolean('is_public')->default(true)->index();
            $table->boolean('is_available')->default(false)->index();
        });

        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->foreignId('sport_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('sport_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
        });

        Schema::create('professional_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('professional_type', 40)->index();
            $table->string('specialisation')->nullable()->index();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->json('certifications')->nullable();
            $table->boolean('is_available')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('organisation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('organisation_name')->index();
            $table->string('organisation_type', 40)->index();
            $table->string('registration_number')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->json('services')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_profiles');
        Schema::dropIfExists('professional_profiles');
        Schema::table('athlete_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('sport_id');
            $table->dropColumn(['height_cm', 'weight_kg']);
        });
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn(['slug', 'cover_image_path', 'is_public', 'is_available']));
        Schema::dropIfExists('positions');
        Schema::dropIfExists('sports');
    }
};
