<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_item_completions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignUlid('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();

            $table->unique(['enrollment_id', 'content_item_id']);
        });

        Schema::create('enrollment_week_completions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignUlid('week_id')->constrained('weeks')->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();

            $table->unique(['enrollment_id', 'week_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_week_completions');
        Schema::dropIfExists('enrollment_item_completions');
    }
};
