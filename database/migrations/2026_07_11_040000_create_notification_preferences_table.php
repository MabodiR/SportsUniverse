<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('messages')->default(true);
            $table->boolean('message_requests')->default(true);
            $table->boolean('opportunities')->default(true);
            $table->boolean('followers')->default(true);
            $table->boolean('engagement')->default(true);
            $table->boolean('moderation')->default(true);
            $table->boolean('profile_views')->default(false);
            $table->boolean('email_digest')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
