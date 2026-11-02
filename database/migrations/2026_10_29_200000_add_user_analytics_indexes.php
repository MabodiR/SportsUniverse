<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'users_created_status_idx');
        });
        Schema::table('login_histories', function (Blueprint $table) {
            $table->index(['method', 'device_type', 'user_id'], 'login_history_source_user_idx');
            $table->index(['platform', 'user_id'], 'login_history_platform_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('login_histories', function (Blueprint $table) {
            $table->dropIndex('login_history_source_user_idx');
            $table->dropIndex('login_history_platform_user_idx');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_created_status_idx');
        });
    }
};
