<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique(); // 'POPULI' | 'CANVAS'
            $table->string('name');
            $table->string('kind'); // SIS | LMS — drives the precedence rule
            $table->unsignedInteger('precedence')->default(1); // lower wins on conflict
            $table->decimal('gpa_scale_max', 5, 2)->default(4.00);
            $table->string('default_currency')->default('EGP');
            $table->string('timezone')->default('Africa/Cairo');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('import_grade_mappings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained('import_sources')->cascadeOnDelete();
            $table->string('legacy_letter')->nullable();
            $table->float('min_percent')->nullable();
            $table->float('max_percent')->nullable();
            $table->string('spims_letter');
            $table->float('gpa_points');
            $table->boolean('is_passing')->default(true);
            $table->boolean('counts_toward_gpa')->default(false);
            $table->timestamps();

            $table->unique(['source_id', 'legacy_letter']);
        });

        Schema::create('import_mapping_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained('import_sources')->cascadeOnDelete();
            $table->string('entity_type');
            $table->string('name');
            $table->json('mappings'); // [{column, target_field, transform, options, confidence, origin}]
            $table->json('constants')->nullable();
            $table->json('ignored_columns')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('proposed_by')->nullable(); // HEURISTIC | AI | MANUAL
            $table->foreignUlid('created_by_id')->constrained('users');
            $table->timestamps();

            $table->unique(['source_id', 'entity_type', 'name']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained('import_sources');
            $table->string('entity_type'); // STUDENT for v1
            $table->string('population')->nullable(); // ALUMNI | ACTIVE
            $table->string('status')->default('DRAFT');
            $table->foreignUlid('mapping_profile_id')->nullable()->constrained('import_mapping_profiles')->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->string('sheet_name')->nullable();
            $table->unsignedInteger('header_row')->default(1);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->json('profile')->nullable(); // column profiling result
            $table->json('mapping')->nullable(); // [{column, target_field, transform, options}]
            $table->json('control_totals')->nullable();
            $table->json('dry_run_report')->nullable();
            $table->json('commit_progress')->nullable();
            $table->timestamp('sealed_at')->nullable();
            $table->foreignUlid('created_by_id')->constrained('users');
            $table->foreignUlid('committed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('rolled_back_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('mapped_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('dry_run_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'entity_type', 'status']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('natural_key');
            $table->json('payload');
            $table->json('normalized')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->string('action')->nullable(); // CREATE | UPDATE | LINK | NOOP
            $table->string('status')->default('PENDING');
            $table->json('messages')->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'natural_key']);
            $table->index(['batch_id', 'status']);
        });

        Schema::create('import_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained('import_sources');
            $table->string('entity_type'); // 'user' | 'student_program' | ...
            $table->string('legacy_id');
            $table->string('target_type');
            $table->string('target_id');
            $table->foreignUlid('first_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->foreignUlid('last_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_id', 'entity_type', 'legacy_id']);
            $table->unique(['source_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
        });

        // One concept, repeated: null means native SPIMS. Legacy ids live in
        // import_links, never in this column — see docs/legacy-data-import-plan.md §4.2.
        Schema::table('users', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('status');
            $table->index('source_system');
        });

        Schema::table('student_programs', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('student_programs', function (Blueprint $table) {
            $table->dropColumn('source_system');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['source_system']);
            $table->dropColumn('source_system');
        });

        Schema::dropIfExists('import_links');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('import_mapping_profiles');
        Schema::dropIfExists('import_grade_mappings');
        Schema::dropIfExists('import_sources');
    }
};
