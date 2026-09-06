<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->string('title');
            $table->foreignUlid('component_id')->nullable()->constrained('gradebook_components')->nullOnDelete();
            $table->unsignedInteger('team_size_min')->default(1);
            $table->unsignedInteger('team_size_max')->default(4);
            $table->timestamp('join_opens_at')->nullable();
            $table->timestamp('join_closes_at')->nullable();
            $table->boolean('allow_leave_once')->default(true);
            $table->string('grading_mode')->default('RUBRIC');
            $table->float('max_points')->default(100);
            $table->string('status')->default('DRAFT');
            $table->timestamp('peer_opens_at')->nullable();
            $table->timestamp('peer_closes_at')->nullable();
            $table->timestamps();

            $table->index(['offering_id', 'status']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_assessment_id')->constrained('project_assessments')->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('OPEN');
            $table->string('workspace_url')->nullable();
            $table->timestamps();

            $table->index('project_assessment_id');
        });

        Schema::create('project_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('MEMBER');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'student_id']);
            $table->index(['student_id', 'left_at']);
        });

        Schema::create('project_membership_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'student_id']);
            $table->index(['student_id', 'kind']);
        });

        Schema::create('project_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_assessment_id')->constrained('project_assessments')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(1);
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->index(['project_assessment_id', 'position']);
        });

        Schema::create('project_deliverables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('phase_id')->constrained('project_phases')->cascadeOnDelete();
            $table->string('kind');
            $table->string('title');
            $table->unsignedInteger('max_files')->default(1);
            $table->unsignedInteger('max_file_mb')->default(10);
            $table->timestamp('due_at')->nullable();
            $table->float('points')->default(0);
            $table->timestamps();

            $table->index('phase_id');
        });

        Schema::create('project_deliverable_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('deliverable_id')->constrained('project_deliverables')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_status')->default('PENDING');
            $table->foreignUlid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('late')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'deliverable_id']);
        });

        Schema::create('project_submission_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained('project_deliverable_submissions')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index('submission_id');
        });

        Schema::create('project_change_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_assessment_id')->constrained('project_assessments')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind');
            $table->text('reason');
            $table->string('status')->default('PENDING');
            $table->foreignUlid('target_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->foreignUlid('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['project_assessment_id', 'status']);
        });

        Schema::create('project_grade_criteria', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_assessment_id')->constrained('project_assessments')->cascadeOnDelete();
            $table->string('name');
            $table->float('weight');
            $table->string('level')->default('TEAM');
            $table->timestamps();

            $table->index('project_assessment_id');
        });

        Schema::create('project_grades', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_assessment_id')->constrained('project_assessments')->cascadeOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignUlid('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('criterion_id')->nullable()->constrained('project_grade_criteria')->nullOnDelete();
            $table->float('score');
            $table->timestamp('announced_at')->nullable();
            $table->timestamps();

            $table->index(['project_assessment_id', 'student_id']);
            $table->index(['project_assessment_id', 'project_id']);
        });

        Schema::create('project_peer_evaluations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUlid('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->float('score');
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['project_id', 'rater_id', 'ratee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_peer_evaluations');
        Schema::dropIfExists('project_grades');
        Schema::dropIfExists('project_grade_criteria');
        Schema::dropIfExists('project_change_requests');
        Schema::dropIfExists('project_submission_files');
        Schema::dropIfExists('project_deliverable_submissions');
        Schema::dropIfExists('project_deliverables');
        Schema::dropIfExists('project_phases');
        Schema::dropIfExists('project_membership_events');
        Schema::dropIfExists('project_memberships');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_assessments');
    }
};
