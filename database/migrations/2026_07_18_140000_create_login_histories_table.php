<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('method', 30)->default('password');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser', 80)->nullable();
            $table->string('platform', 80)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->timestamp('logged_in_at')->useCurrent();
            $table->index(['user_id', 'logged_in_at']);
            $table->index(['user_id', 'ip_address']);
        });
    }

    public function down(): void { Schema::dropIfExists('login_histories'); }
};
