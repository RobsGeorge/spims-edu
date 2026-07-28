<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('courses');
            $table->foreignUlid('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('mode')->default('COHORT');
            $table->unsignedBigInteger('price_usd_override')->nullable();
            $table->unsignedBigInteger('price_egp_override')->nullable();
            $table->unsignedInteger('seat_capacity')->nullable();
            $table->float('attendance_threshold_percent')->default(60);
            $table->string('status')->default('DRAFT');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('course_id');
            $table->index('semester_id');
        });

        Schema::create('offering_staff', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users');
            $table->string('role');

            $table->unique(['offering_id', 'user_id', 'role']);
        });

        Schema::create('weeks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->timestamp('unlock_date')->nullable();
            $table->unsignedInteger('order');

            $table->unique(['offering_id', 'number']);
        });

        Schema::create('content_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('week_id')->constrained('weeks')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->unsignedInteger('order');
            $table->string('vimeo_id')->nullable();
            $table->string('file_url')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('content_item_id')->unique()->constrained('content_items')->cascadeOnDelete();
            $table->ulid('component_id')->nullable();
            $table->text('instructions');
            $table->string('submission_type')->default('BOTH');
            $table->json('allowed_file_types');
            $table->float('max_points')->default(100);
            $table->float('item_weight')->nullable();
            $table->boolean('released')->default(false);
            $table->timestamp('due_date')->nullable();
            $table->float('late_penalty_override')->nullable();
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->text('text_body')->nullable();
            $table->string('file_url')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->boolean('is_late')->default(false);
            $table->float('raw_score')->nullable();
            $table->float('final_score')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignUlid('graded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();

            $table->unique(['assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('weeks');
        Schema::dropIfExists('offering_staff');
        Schema::dropIfExists('course_offerings');
    }
};
