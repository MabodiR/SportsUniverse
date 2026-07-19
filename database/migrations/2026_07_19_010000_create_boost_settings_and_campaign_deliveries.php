<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boost_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('cpm_cents')->default(5000);
            $table->unsignedTinyInteger('organic_posts_between')->default(8);
            $table->unsignedTinyInteger('frequency_cap_per_day')->default(3);
            $table->unsignedInteger('minimum_daily_budget_cents')->default(5000);
            $table->unsignedInteger('maximum_daily_budget_cents')->default(1000000);
            $table->unsignedTinyInteger('maximum_duration_days')->default(90);
            $table->boolean('require_review')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('boost_settings')->insert([
            'enabled' => true, 'cpm_cents' => 5000, 'organic_posts_between' => 8,
            'frequency_cap_per_day' => 3, 'minimum_daily_budget_cents' => 5000,
            'maximum_daily_budget_cents' => 1000000, 'maximum_duration_days' => 90,
            'require_review' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('campaign_deliveries', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_hash', 64)->nullable();
            $table->date('served_on')->index();
            $table->timestamp('served_at');
            $table->timestamp('impressed_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('video_viewed_at')->nullable();
            $table->timestamp('profile_visited_at')->nullable();
            $table->timestamp('followed_at')->nullable();
            $table->unsignedInteger('charge_cents')->default(0);
            $table->string('placement', 30)->default('for_you_feed');
            $table->timestamps();
            $table->index(['campaign_id', 'served_on', 'impressed_at']);
            $table->index(['user_id', 'campaign_id', 'served_on']);
            $table->index(['session_hash', 'campaign_id', 'served_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_deliveries');
        Schema::dropIfExists('boost_settings');
    }
};
