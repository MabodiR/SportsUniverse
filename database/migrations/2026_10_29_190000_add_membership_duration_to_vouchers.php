<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->unsignedSmallInteger('membership_duration_value')->default(1)->after('max_redemptions_per_user');
            $table->string('membership_duration_unit', 12)->default('months')->after('membership_duration_value');
        });

        DB::table('promotion_announcements')
            ->where('message', 'The first 200 members receive our starter package free.')
            ->update(['message' => 'The first :limit members receive our :plan package free for :duration.']);
    }

    public function down(): void
    {
        DB::table('promotion_announcements')
            ->where('message', 'The first :limit members receive our :plan package free for :duration.')
            ->update(['message' => 'The first 200 members receive our starter package free.']);

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn(['membership_duration_value', 'membership_duration_unit']);
        });
    }
};
