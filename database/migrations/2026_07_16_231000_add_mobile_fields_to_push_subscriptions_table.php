<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->string('provider', 30)->default('web')->index();
            $table->string('platform', 30)->nullable();
            $table->string('device_name', 160)->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', fn (Blueprint $table) => $table->dropColumn(['provider', 'platform', 'device_name', 'last_seen_at']));
    }
};
