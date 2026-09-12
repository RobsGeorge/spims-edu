<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rung 6 of the matching ladder — the human identity review queue. Anything that
 * reaches the end of the ladder without a deterministic match lands here instead of
 * being auto-merged or silently created. See docs/legacy-data-import-plan.md §4.1, §6,
 * §11.10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_merge_candidates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_id')->constrained('import_sources');
            $table->string('legacy_id');
            $table->foreignUlid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->foreignUlid('candidate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->float('score')->default(0);
            $table->json('matched_on')->nullable(); // e.g. ['name_similarity'], ['name','date_of_birth']
            $table->json('payload_preview')->nullable(); // the sheet row, for side-by-side compare
            $table->string('status')->default('PENDING'); // PENDING | MERGED | REJECTED | NEW_USER
            $table->foreignUlid('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_merge_candidates');
    }
};
