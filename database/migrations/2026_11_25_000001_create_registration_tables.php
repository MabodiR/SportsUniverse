<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('verification_required')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->text('payload');
            $table->string('channel')->default('email');
            $table->string('otp_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('delivery_status')->default('pending');
            $table->string('status')->default('pending')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
        Schema::dropIfExists('registration_settings');
    }
};
