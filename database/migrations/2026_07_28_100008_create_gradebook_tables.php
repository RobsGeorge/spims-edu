<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gradebook_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->string('name');
            $table->float('weight_percent');
            $table->string('kind');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreign('component_id')
                ->references('id')
                ->on('gradebook_components')
                ->nullOnDelete();
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->foreign('component_id')
                ->references('id')
                ->on('gradebook_components')
                ->nullOnDelete();
        });

        Schema::create('student_programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->foreignUlid('program_id')->constrained('programs');
            $table->string('status')->default('ACTIVE');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->float('cached_gpa')->nullable();

            $table->unique(['student_id', 'program_id']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->foreignUlid('offering_id')->constrained('course_offerings');
            $table->foreignUlid('student_program_id')->nullable()->constrained('student_programs')->nullOnDelete();
            $table->string('status')->default('ENROLLED');
            $table->boolean('is_audit')->default(false);
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('dropped_at')->nullable();
            $table->string('grade_type')->default('IN_PROGRESS');
            $table->float('final_percent')->nullable();
            $table->string('final_letter')->nullable();
            $table->float('final_gpa_points')->nullable();
            $table->string('grade_status')->default('IN_PROGRESS');
            $table->foreignUlid('grade_locked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('grade_locked_at')->nullable();
            $table->float('progress_percent')->default(0);

            $table->unique(['student_id', 'offering_id']);
            $table->index('status');
        });

        Schema::create('academic_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->foreignUlid('course_id')->constrained('courses');
            $table->foreignUlid('enrollment_id')->nullable()->unique()->constrained('enrollments')->nullOnDelete();
            $table->string('letter_grade');
            $table->float('percent');
            $table->float('gpa_points');
            $table->unsignedInteger('credit_hours');
            $table->string('term');
            $table->boolean('is_passing');
            $table->timestamp('completed_at')->useCurrent();

            $table->index(['student_id', 'course_id']);
        });

        Schema::create('program_requirement_fulfillments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_program_id')->constrained('student_programs')->cascadeOnDelete();
            $table->foreignUlid('program_course_id')->constrained('program_courses');
            $table->foreignUlid('academic_record_id')->constrained('academic_records');
            $table->timestamp('applied_at')->useCurrent();

            $table->unique(['student_program_id', 'program_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_requirement_fulfillments');
        Schema::dropIfExists('academic_records');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('student_programs');

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropForeign(['component_id']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['component_id']);
        });

        Schema::dropIfExists('gradebook_components');
    }
};
