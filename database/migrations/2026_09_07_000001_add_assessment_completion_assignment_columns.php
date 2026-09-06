<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S5 remaining work on top of the already-landed resubmission-overwrite fix
 * (`2026_09_05_000001_add_assignment_submission_versioning.php`).
 *
 * `allow_resubmission`, `attempt_no`, and `late_penalty_override` already exist — this
 * migration only adds what is genuinely missing: delivery mode and the resubmission
 * deadline window on `assignments`, and the physical hand-in markers on
 * `assignment_submissions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->string('delivery_mode')->default('ONLINE')->after('allow_resubmission');
            $table->timestamp('resubmission_deadline')->nullable()->after('delivery_mode');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('attempt_no');
            $table->foreignUlid('received_by_id')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_id');
            $table->dropColumn('received_at');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['delivery_mode', 'resubmission_deadline']);
        });
    }
};
