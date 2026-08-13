<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Week completions already exist from portal I2 (2026_07_29_150000).
        // This migration only adds item-level completions for the learn player.
        if (! Schema::hasTable('enrollment_item_completions')) {
            Schema::create('enrollment_item_completions', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
                $table->foreignUlid('content_item_id')->constrained('content_items')->cascadeOnDelete();
                $table->timestamp('completed_at')->useCurrent();

                $table->unique(['enrollment_id', 'content_item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_item_completions');
    }
};
