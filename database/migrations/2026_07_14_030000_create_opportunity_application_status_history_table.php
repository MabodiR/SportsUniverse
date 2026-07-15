<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opportunity_application_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['opportunity_application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_application_status_history');
    }
};
