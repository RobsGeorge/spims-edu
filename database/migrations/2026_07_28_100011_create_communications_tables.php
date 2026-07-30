<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->string('channel')->default('IN_APP');
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->constrained('course_offerings')->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users');
            $table->string('title');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('discussion_boards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('offering_id')->unique()->constrained('course_offerings')->cascadeOnDelete();
            $table->boolean('allow_student_threads')->default(true);
        });

        Schema::create('discussion_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('board_id')->constrained('discussion_boards')->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users');
            $table->string('title');
            $table->string('visibility')->default('OPEN');
            $table->boolean('is_graded')->default(false);
            $table->unsignedInteger('participation_min_words')->nullable();
            $table->unsignedInteger('participation_min_posts')->nullable();
            $table->unsignedInteger('participation_min_replies')->nullable();
            $table->boolean('locked')->default(false);
            $table->boolean('pinned')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        // PostgreSQL rejects an inline self-referential ULID FK during CREATE TABLE
        // ("no unique constraint matching given keys"). Create the column first,
        // then attach the FK after the primary key exists.
        Schema::create('discussion_posts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('thread_id')->constrained('discussion_threads')->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users');
            $table->ulid('parent_post_id')->nullable()->index();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
        });

        Schema::table('discussion_posts', function (Blueprint $table) {
            $table->foreign('parent_post_id')
                ->references('id')
                ->on('discussion_posts')
                ->nullOnDelete();
        });

        Schema::create('discussion_grades', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('thread_id')->constrained('discussion_threads')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users');
            $table->float('auto_score')->nullable();
            $table->float('final_score')->nullable();
            $table->boolean('overridden')->default(false);
            $table->text('feedback')->nullable();
            $table->foreignUlid('graded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();

            $table->unique(['thread_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_grades');
        Schema::dropIfExists('discussion_posts');
        Schema::dropIfExists('discussion_threads');
        Schema::dropIfExists('discussion_boards');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
    }
};
