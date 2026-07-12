<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0)->index();
        });

        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 40)->default('profile');
            $table->date('viewed_on');
            $table->timestamps();
            $table->unique(['profile_user_id', 'viewer_id', 'viewed_on']);
            $table->index(['profile_user_id', 'viewed_on']);
            $table->index(['viewer_id', 'viewed_on']);
        });

        Schema::create('analytics_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('dimension_type', 30);
            $table->unsignedBigInteger('dimension_id');
            $table->string('metric', 50);
            $table->date('metric_date');
            $table->unsignedBigInteger('value')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['dimension_type', 'dimension_id', 'metric', 'metric_date'], 'daily_metric_unique');
            $table->index(['dimension_type', 'metric', 'metric_date']);
            $table->index(['dimension_type', 'dimension_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_daily_metrics');
        Schema::dropIfExists('profile_views');
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropColumn('views_count'));
    }
};
