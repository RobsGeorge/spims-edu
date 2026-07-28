<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name');
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->string('type');
            $table->text('prompt');
            $table->float('points')->default(1);
            $table->json('config')->nullable();
            $table->text('ai_key_points')->nullable();
            $table->text('ai_guidance')->nullable();
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->string('match_key')->nullable();
            $table->unsignedInteger('order')->default(0);
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignUlid('content_item_id')->nullable()->unique()->constrained('content_items')->nullOnDelete();
            $table->ulid('component_id')->nullable();
            $table->string('mode');
            $table->string('title');
            $table->string('language', 10)->default('en');
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->unsignedInteger('attempts_allowed')->default(1);
            $table->string('scoring_rule')->default('HIGHEST');
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->foreignUlid('draw_from_bank_id')->nullable()->constrained('question_banks')->nullOnDelete();
            $table->unsignedInteger('questions_to_draw')->nullable();
            $table->string('results_visibility')->default('ON_RELEASE');
            $table->boolean('reveal_answers')->default(false);
            $table->boolean('enforce_full_screen')->default(false);
            $table->boolean('one_at_a_time')->default(false);
            $table->boolean('no_backtrack')->default(false);
            $table->boolean('log_focus_loss')->default(true);
            $table->float('max_points')->default(100);
            $table->float('item_weight')->nullable();
            $table->boolean('released')->default(false);
            $table->timestamps();
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('questions');
            $table->unsignedInteger('order')->default(0);
            $table->float('points_override')->nullable();

            $table->unique(['assessment_id', 'question_id']);
        });

        Schema::create('assessment_attempts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->unsignedInteger('attempt_no')->default(1);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('due_at');
            $table->timestamp('submitted_at')->nullable();
            $table->string('status')->default('IN_PROGRESS');
            $table->float('total_score')->nullable();
            $table->unsignedInteger('focus_loss_count')->default(0);
            $table->json('question_ids')->nullable();
            $table->json('exam_snapshot')->nullable();

            $table->unique(['assessment_id', 'student_id', 'attempt_no']);
            $table->index('status');
            $table->index(['status', 'due_at']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('attempt_id')->constrained('assessment_attempts')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('questions');
            $table->json('response')->nullable();
            $table->float('auto_score')->nullable();
            $table->float('ai_suggested_score')->nullable();
            $table->text('ai_rationale')->nullable();
            $table->float('final_score')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignUlid('graded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('assessment_attempts');
        Schema::dropIfExists('assessment_questions');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_banks');
    }
};
