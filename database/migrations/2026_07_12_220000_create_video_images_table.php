<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_images', function (Blueprint $table) {
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->primary(['video_id', 'media_id']);
            $table->index(['video_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_images');
    }
};
