<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. `assignment_submissions` keeps its unique (assignment_id, student_id)
 * row as the current submission; prior states are archived here instead of being
 * overwritten in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('allow_resubmission')->default(true);
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->unsignedInteger('attempt_no')->default(1);
        });

        Schema::create('assignment_submission_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('submission_id')->constrained('assignment_submissions')->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->text('text_body')->nullable();
            $table->string('file_url')->nullable();
            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->float('raw_score')->nullable();
            $table->float('final_score')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignUlid('graded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();

            $table->unique(['submission_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submission_versions');

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn('attempt_no');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('allow_resubmission');
        });
    }
};
