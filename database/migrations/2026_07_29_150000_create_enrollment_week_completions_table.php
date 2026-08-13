<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_week_completions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignUlid('week_id')->constrained('weeks')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unique(['enrollment_id', 'week_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_week_completions');
    }
};
