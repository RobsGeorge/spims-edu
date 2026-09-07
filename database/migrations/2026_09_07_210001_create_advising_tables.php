<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'advisor_id', 'program_id']);
        });

        Schema::create('advising_holds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind');
            $table->text('reason');
            $table->foreignUlid('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignUlid('released_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advising_holds');
        Schema::dropIfExists('advisor_assignments');
    }
};
