<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_recurrences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->json('days_of_week');
            $table->string('start_time');
            $table->unsignedInteger('duration_minutes');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
        });

        Schema::create('live_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('scheduled_start');
            $table->unsignedInteger('duration_minutes');
            $table->string('zoom_meeting_id')->nullable();
            $table->string('zoom_join_url')->nullable();
            $table->string('zoom_start_url')->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('reminder_15m_sent_at')->nullable();

            $table->index(['offering_id', 'scheduled_start']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('live_session_id')->constrained('live_sessions')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->string('status');
            $table->unsignedInteger('minutes_attended')->default(0);
            $table->string('source')->default('ZOOM_IMPORT');
            $table->foreignUlid('overridden_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['live_session_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('live_sessions');
        Schema::dropIfExists('session_recurrences');
    }
};
