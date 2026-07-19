<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_interval', 20);
            $table->string('provider', 30)->default('payfast');
            $table->string('merchant_reference', 100)->unique();
            $table->string('provider_payment_id')->nullable()->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('ZAR');
            $table->string('status', 30)->default('pending')->index();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('subscription_payments'); }
};
