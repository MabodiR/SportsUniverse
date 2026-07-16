<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('athlete_career_entries', function (Blueprint $table) {
            $table->foreignId('sport_id')->nullable()->after('team_name')->constrained()->nullOnDelete();
            $table->foreignId('position_id')->nullable()->after('sport_id')->constrained('positions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('athlete_career_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('sport_id');
        });
    }
};
