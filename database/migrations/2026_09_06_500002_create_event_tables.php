<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('venue')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('DRAFT');
            $table->json('eligibility');
            $table->boolean('waitlist_enabled')->default(false);
            $table->foreignUlid('created_by')->constrained('users');
            $table->timestamps();

            $table->index('status');
            $table->index('starts_at');
        });

        Schema::create('event_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('reserved_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        // One open reservation per student per event. Cancelled rows drop out of
        // the index so a later reserve is allowed (new row, not an in-place reuse).
        DB::statement("CREATE UNIQUE INDEX event_reservations_open_unique ON event_reservations (event_id, student_id) WHERE status <> 'CANCELLED'");

        Schema::create('event_reservation_exceptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind');
            $table->timestamps();

            $table->unique(['event_id', 'student_id']);
        });

        Schema::create('event_check_ins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('reservation_id')->unique()->constrained('event_reservations')->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->foreignUlid('checked_in_by_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('event_admins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_admins');
        Schema::dropIfExists('event_check_ins');
        Schema::dropIfExists('event_reservation_exceptions');
        Schema::dropIfExists('event_reservations');
        Schema::dropIfExists('events');
    }
};
