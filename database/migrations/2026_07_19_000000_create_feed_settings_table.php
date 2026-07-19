<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_settings', function (Blueprint $table) {
            $table->id();
            $table->string('ranking_mode')->default('personalized');
            $table->decimal('view_weight', 8, 2)->default(0.05);
            $table->decimal('like_weight', 8, 2)->default(3);
            $table->decimal('comment_weight', 8, 2)->default(4);
            $table->decimal('share_weight', 8, 2)->default(5);
            $table->decimal('follow_boost', 10, 2)->default(1000);
            $table->unsignedSmallInteger('page_size')->default(15);
            $table->unsignedSmallInteger('recommendation_size')->default(500);
            $table->boolean('use_fan_sports')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('feed_settings')->insert([
            'ranking_mode' => 'personalized',
            'view_weight' => 0.05,
            'like_weight' => 3,
            'comment_weight' => 4,
            'share_weight' => 5,
            'follow_boost' => 1000,
            'page_size' => 15,
            'recommendation_size' => 500,
            'use_fan_sports' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_settings');
    }
};
