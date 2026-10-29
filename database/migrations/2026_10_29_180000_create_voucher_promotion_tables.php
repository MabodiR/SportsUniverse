<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('campaign_name', 160)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('discount_type', 30);
            $table->unsignedInteger('discount_value')->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_redemptions_per_user')->default(1);
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('new_users_only')->default(false);
            $table->boolean('auto_apply')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'auto_apply', 'starts_at', 'expires_at']);
        });

        Schema::create('subscription_plan_voucher', function (Blueprint $table) {
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->primary(['voucher_id', 'subscription_plan_id']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('original_amount_cents')->default(0);
            $table->unsignedInteger('discount_amount_cents')->default(0);
            $table->unsignedInteger('final_amount_cents')->default(0);
            $table->timestamp('redeemed_at');
            $table->timestamps();
            $table->index(['voucher_id', 'user_id']);
            $table->index(['redeemed_at', 'subscription_plan_id']);
        });

        Schema::create('promotion_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('message');
            $table->string('cta_label', 80)->default('Claim your free membership');
            $table->string('cta_url')->default('/register');
            $table->boolean('show_banner')->default(true);
            $table->boolean('show_popup')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('voucher_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->json('changes')->nullable();
            $table->timestamps();
            $table->index(['voucher_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_audits');
        Schema::dropIfExists('promotion_announcements');
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('subscription_plan_voucher');
        Schema::dropIfExists('vouchers');
    }
};
