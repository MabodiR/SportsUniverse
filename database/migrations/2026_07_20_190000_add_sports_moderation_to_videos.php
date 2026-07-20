<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->decimal('sports_relevance_score', 5, 4)->nullable()->index();
            $table->string('moderation_recommendation', 32)->nullable()->index();
            $table->text('moderation_reason')->nullable();
            $table->timestamp('moderation_analyzed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['sports_relevance_score', 'moderation_recommendation', 'moderation_reason', 'moderation_analyzed_at']);
        });
    }
};
