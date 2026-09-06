<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_surveys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->nullable()->constrained('course_offerings')->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('DRAFT');
            $table->boolean('anonymous_default')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->foreignUlid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'offering_id']);
        });

        Schema::create('feedback_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('survey_id')->constrained('feedback_surveys')->cascadeOnDelete();
            $table->text('prompt');
            $table->string('kind');
            $table->json('options')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('required')->default(true);
            $table->timestamps();

            $table->index(['survey_id', 'position']);
        });

        // Anonymous by default: student_id must never live on this row.
        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('survey_id')->constrained('feedback_surveys')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->boolean('is_anonymous')->default(true);
            $table->timestamps();

            $table->index('survey_id');
        });

        Schema::create('feedback_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained('feedback_submissions')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('feedback_questions')->cascadeOnDelete();
            $table->json('value');
            $table->timestamps();

            $table->unique(['submission_id', 'question_id']);
        });

        // Sealed identity. Readable only after an approved reveal request.
        Schema::create('feedback_submission_identities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained('feedback_submissions')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('submission_id');
            $table->index('student_id');
        });

        Schema::create('feedback_identity_reveal_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained('feedback_submissions')->cascadeOnDelete();
            $table->foreignUlid('requester_id')->constrained('users');
            $table->string('status')->default('PENDING');
            $table->foreignUlid('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_identity_reveal_requests');
        Schema::dropIfExists('feedback_submission_identities');
        Schema::dropIfExists('feedback_answers');
        Schema::dropIfExists('feedback_submissions');
        Schema::dropIfExists('feedback_questions');
        Schema::dropIfExists('feedback_surveys');
    }
};
