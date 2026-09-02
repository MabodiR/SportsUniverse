<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24);
            $table->string('path', 500);
            $table->unsignedInteger('duration_ms');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['occurred_at', 'kind']);
            $table->index(['path', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_performance_metrics');
    }
};
