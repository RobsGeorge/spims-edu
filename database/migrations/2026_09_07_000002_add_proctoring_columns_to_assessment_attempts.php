<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `focus_loss_count` (already on `assessment_attempts`) is the raw tally of every
 * proctor event recorded for an attempt. `proctor_warnings` is a distinct concept added
 * here: the running count used to decide escalation, mirrored onto the attempt so it can
 * be read without joining `proctor_events`. In this phase every proctor event increments
 * both counters together, so they stay numerically identical — the distinction exists so
 * a future event type could be "logged but not counted toward escalation" without
 * touching the escalation threshold logic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->unsignedInteger('proctor_warnings')->default(0)->after('focus_loss_count');
            $table->boolean('terminated_for_cheating')->default(false)->after('proctor_warnings');
            $table->timestamp('terminated_at')->nullable()->after('terminated_for_cheating');
            $table->foreignUlid('terminated_by_id')->nullable()->after('terminated_at')->constrained('users')->nullOnDelete();
            // Kept distinct from terminated_at/terminated_by_id so the original
            // termination stays on the record even after an admin override clears it.
            $table->timestamp('termination_cleared_at')->nullable()->after('terminated_by_id');
            $table->foreignUlid('termination_cleared_by_id')->nullable()->after('termination_cleared_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('termination_cleared_by_id');
            $table->dropColumn('termination_cleared_at');
            $table->dropConstrainedForeignId('terminated_by_id');
            $table->dropColumn(['terminated_at', 'terminated_for_cheating', 'proctor_warnings']);
        });
    }
};
