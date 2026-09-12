<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L3 — course results, GPA, shadow catalog. See docs/legacy-data-import-plan.md §4.2, §7.
 *
 * `users` and `student_programs` already carry `source_system` from the L0/L1 migration
 * (2026_09_11_000001_create_legacy_import_tables.php) and are not touched again here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One concept, repeated: null means native SPIMS. See §4.2.
        Schema::table('programs', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('active');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('active');
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('status');
            // The legacy term string a shadow offering was created for — lets
            // ImportBatchService find-or-create one shadow offering per (course, term)
            // without a `semesters` row (plan §14 Q5 default). Always null for native
            // offerings, which key uniqueness off `semester_id` instead.
            $table->string('legacy_term')->nullable()->after('source_system');
            $table->index('source_system');
            $table->unique(['course_id', 'legacy_term', 'source_system']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('progress_percent');
            $table->index('source_system');
        });

        Schema::table('academic_records', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('completed_at');
            // Default true is what makes GradebookService::refreshGpa()'s new filter a
            // no-op for every existing native record — see §7.
            $table->boolean('counts_toward_gpa')->default(true)->after('source_system');
            $table->index('source_system');
        });

        // §4.1 — the attested legacy GPA figure, rendered beside the live SPIMS one,
        // never merged into it (D3).
        Schema::create('legacy_academic_summaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->foreignUlid('source_id')->constrained('import_sources');
            $table->foreignUlid('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignUlid('student_program_id')->nullable()->constrained('student_programs')->nullOnDelete();
            $table->string('scope'); // PROGRAM | CUMULATIVE
            $table->decimal('gpa', 6, 3)->nullable();
            $table->decimal('gpa_scale', 5, 2); // as reported, never converted
            $table->unsignedInteger('credits_attempted')->default(0);
            $table->unsignedInteger('credits_earned')->default(0);
            $table->string('standing')->nullable(); // the source's own words
            $table->string('honors')->nullable();
            $table->date('as_of');
            $table->foreignUlid('attested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('attested_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'source_id', 'scope', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_academic_summaries');

        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropIndex(['source_system']);
            $table->dropColumn(['source_system', 'counts_toward_gpa']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['source_system']);
            $table->dropColumn('source_system');
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'legacy_term', 'source_system']);
            $table->dropIndex(['source_system']);
            $table->dropColumn(['source_system', 'legacy_term']);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('source_system');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('source_system');
        });
    }
};
