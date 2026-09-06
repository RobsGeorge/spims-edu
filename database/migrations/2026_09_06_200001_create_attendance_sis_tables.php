<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->string('title');
            $table->timestamp('scheduled_start');
            $table->unsignedInteger('duration_minutes');
            $table->string('mode');
            $table->string('location')->nullable();
            $table->foreignUlid('live_session_id')->nullable()->constrained('live_sessions')->nullOnDelete();
            $table->timestamp('attendance_closed_at')->nullable();
            $table->boolean('notify_students')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['offering_id', 'scheduled_start']);
            $table->unique('live_session_id');
        });

        Schema::create('attendance_policy', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->nullable()->constrained('course_offerings')->cascadeOnDelete();
            $table->unsignedTinyInteger('min_percentage')->default(75);
            $table->unsignedTinyInteger('late_grade_percentage')->default(50);
            $table->boolean('counts_toward_grade')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique('offering_id');
        });

        Schema::create('attendance_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->string('status');
            $table->text('excuse_reason')->nullable();
            $table->unsignedInteger('minutes_attended')->default(0);
            $table->string('source')->default('MANUAL');
            $table->foreignUlid('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->useCurrent();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['class_session_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('attendance_check_in_codes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->string('code');
            $table->timestamp('expires_at');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamps();

            $table->index(['class_session_id', 'code']);
        });

        Schema::create('session_notification_targets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['class_session_id', 'user_id']);
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignUlid('class_session_id')->nullable()->constrained('class_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_session_id');
        });

        Schema::dropIfExists('session_notification_targets');
        Schema::dropIfExists('attendance_check_in_codes');
        Schema::dropIfExists('attendance_entries');
        Schema::dropIfExists('attendance_policy');
        Schema::dropIfExists('class_sessions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }
};
