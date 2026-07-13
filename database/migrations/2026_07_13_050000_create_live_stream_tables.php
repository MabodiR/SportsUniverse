<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('live');
            $table->unsignedInteger('viewer_count')->default(0);
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'started_at']);
        });
        Schema::create('live_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('reaction', 20)->nullable();
            $table->timestamps();
            $table->index(['live_stream_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_messages');
        Schema::dropIfExists('live_streams');
    }
};
