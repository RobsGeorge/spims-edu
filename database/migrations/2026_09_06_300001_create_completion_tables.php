<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completion_criteria', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignUlid('offering_id')->nullable()->constrained('course_offerings')->cascadeOnDelete();
            $table->string('kind');
            $table->float('threshold')->nullable();
            $table->foreignUlid('content_item_id')->nullable()->constrained('content_items')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['course_id', 'kind']);
            $table->index(['offering_id', 'kind']);
        });

        Schema::create('offering_closings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->string('status')->default('OPEN');
            $table->json('grace_marks')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignUlid('locked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('announced_at')->nullable();
            $table->foreignUlid('announced_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUlid('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('offering_id');
        });

        Schema::create('completion_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->json('met_criteria');
            $table->string('outcome')->default('PENDING');
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(['offering_id', 'student_id']);
            $table->index(['offering_id', 'outcome']);
        });

        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title');
            $table->text('body');
            $table->string('background_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();

            // Uniqueness (at most one row per (course_id, locale), at most one global
            // row per locale) is enforced in CertificateTemplateService, not a DB
            // constraint — the same approach this codebase already takes for
            // EmailTemplate's identically-shaped nullable-scope uniqueness problem
            // (see email_templates in create_communications_spine_tables.php, which
            // also has no unique index over scope_type/scope_id/key/locale).
            $table->index(['course_id', 'locale']);
        });

        Schema::create('student_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users');
            $table->text('body');
            $table->string('visibility')->default('STAFF_ONLY');
            $table->timestamps();

            $table->index(['offering_id', 'student_id']);
        });

        Schema::create('module_student_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('week_id')->constrained('weeks')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->foreignUlid('assessed_by_id')->constrained('users');
            $table->timestamps();

            // Per-week, per-student — re-assessment updates the existing row in place
            // rather than accumulating a history, matching the spec's "per-week ... per
            // student" phrasing and mirroring completion_results' upsert semantics.
            $table->unique(['week_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_student_assessments');
        Schema::dropIfExists('student_notes');
        Schema::dropIfExists('certificate_templates');
        Schema::dropIfExists('completion_results');
        Schema::dropIfExists('offering_closings');
        Schema::dropIfExists('completion_criteria');
    }
};
