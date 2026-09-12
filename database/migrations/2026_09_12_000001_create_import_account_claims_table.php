<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L5 — bulk account-claim invitations for the ACTIVE population.
 * See docs/legacy-data-import-plan.md §4.1 and §9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_account_claims', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('status')->default('QUEUED'); // QUEUED|SENT|CLAIMED|BOUNCED|EXPIRED|CANCELLED
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
            $table->unsignedInteger('reminder_count')->default(0);
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_account_claims');
    }
};
