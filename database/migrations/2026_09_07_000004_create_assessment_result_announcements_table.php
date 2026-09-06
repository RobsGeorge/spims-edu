<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_result_announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assessment_id')->unique()->constrained('assessments')->cascadeOnDelete();
            $table->timestamp('announced_at');
            $table->foreignUlid('announced_by_id')->nullable()->constrained('users')->nullOnDelete();
            // Tracks who has already been notified so re-announcing (e.g. after correcting a
            // score) can touch up `announced_at` without re-sending to students who already
            // got the first notification. See AssessmentService::announceResults().
            $table->json('notified_student_ids')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_result_announcements');
    }
};
