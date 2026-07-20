<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->string('post_type', 20)->default('post')->index()->after('status');
            $table->timestamp('expires_at')->nullable()->index()->after('published_at');
            $table->index(['post_type', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['post_type', 'status', 'expires_at']);
            $table->dropColumn(['post_type', 'expires_at']);
        });
    }
};
