<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->change();
            $table->string('location_name')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('comments_enabled')->default(true);
        });
    }
    public function down(): void {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['location_name','latitude','longitude','comments_enabled']);
        });
    }
};
