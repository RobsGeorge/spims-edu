<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only log, matching `session_notification_targets` / `communication_logs`
        // conventions: created_at only, no updated_at.
        Schema::create('proctor_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->string('event_type');
            $table->unsignedInteger('warning_number');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['attempt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctor_events');
    }
};
