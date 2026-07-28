<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('grade_bands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('scheme_id')->constrained('grading_schemes')->cascadeOnDelete();
            $table->string('letter');
            $table->float('min_percent');
            $table->float('max_percent');
            $table->float('gpa_points');
            $table->boolean('is_passing');
        });

        Schema::create('assessment_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('assessment_template_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('template_id')->constrained('assessment_templates')->cascadeOnDelete();
            $table->string('name');
            $table->float('weight_percent');
            $table->string('kind');
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->float('passing_threshold')->default(60);
            $table->unsignedInteger('max_credits_per_semester');
            $table->unsignedInteger('max_courses_per_semester');
            $table->unsignedInteger('max_semesters_to_graduate');
            $table->unsignedInteger('elective_credits_required')->default(0);
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->foreignUlid('grading_scheme_id')->nullable()->constrained('grading_schemes')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->string('title');
            $table->unsignedInteger('credit_hours');
            $table->unsignedBigInteger('default_price_usd')->default(0);
            $table->unsignedBigInteger('default_price_egp')->default(0);
            $table->boolean('is_free')->default(false);
            $table->boolean('is_standalone')->default(false);
            $table->float('passing_threshold')->nullable();
            $table->foreignUlid('assessment_template_id')->nullable()->constrained('assessment_templates')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignUlid('prerequisite_id')->constrained('courses');

            $table->unique(['course_id', 'prerequisite_id']);
        });

        Schema::create('program_courses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignUlid('course_id')->constrained('courses');
            $table->string('requirement');
            $table->unsignedInteger('year_level')->nullable();

            $table->unique(['program_id', 'course_id']);
        });

        Schema::create('course_interest_flags', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->foreignUlid('course_id')->constrained('courses');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_interest_flags');
        Schema::dropIfExists('program_courses');
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('assessment_template_components');
        Schema::dropIfExists('assessment_templates');
        Schema::dropIfExists('grade_bands');
        Schema::dropIfExists('grading_schemes');
    }
};
