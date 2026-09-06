<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_quizzes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->string('title');
            $table->string('status');
            $table->foreignUlid('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['offering_id', 'status']);
        });

        Schema::create('live_quiz_questions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('quiz_id')->constrained('live_quizzes')->cascadeOnDelete();
            $table->text('prompt');
            $table->unsignedInteger('position');
            $table->unsignedInteger('time_limit_seconds');
            $table->unsignedInteger('points');
            $table->timestamps();

            $table->unique(['quiz_id', 'position']);
        });

        Schema::create('live_quiz_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_id')->constrained('live_quiz_questions')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['question_id', 'position']);
        });

        Schema::create('live_quiz_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('quiz_id')->constrained('live_quizzes')->cascadeOnDelete();
            $table->string('join_code', 8)->unique();
            $table->string('state');
            $table->foreignUlid('current_question_id')->nullable()->constrained('live_quiz_questions')->nullOnDelete();
            $table->timestamp('question_opened_at')->nullable();
            $table->timestamp('question_closes_at')->nullable();
            $table->foreignUlid('host_id')->constrained('users');
            $table->timestamps();

            $table->index(['quiz_id', 'state']);
        });

        Schema::create('live_quiz_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('live_quiz_sessions')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->string('display_name');
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
        });

        Schema::create('live_quiz_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('live_quiz_sessions')->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained('live_quiz_questions')->cascadeOnDelete();
            $table->foreignUlid('participant_id')->constrained('live_quiz_participants')->cascadeOnDelete();
            $table->foreignUlid('option_id')->constrained('live_quiz_options');
            $table->timestamp('answered_at');
            $table->unsignedInteger('score')->default(0);
            $table->timestamps();

            $table->unique(['session_id', 'question_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_quiz_answers');
        Schema::dropIfExists('live_quiz_participants');
        Schema::dropIfExists('live_quiz_sessions');
        Schema::dropIfExists('live_quiz_options');
        Schema::dropIfExists('live_quiz_questions');
        Schema::dropIfExists('live_quizzes');
    }
};
