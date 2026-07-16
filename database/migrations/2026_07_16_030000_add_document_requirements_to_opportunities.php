<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->json('required_documents')->nullable()->after('requirements');
        });

        Schema::create('opportunity_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->string('requirement_key', 80);
            $table->timestamps();
            $table->unique(['opportunity_application_id', 'requirement_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_application_documents');
        Schema::table('opportunities', fn (Blueprint $table) => $table->dropColumn('required_documents'));
    }
};
