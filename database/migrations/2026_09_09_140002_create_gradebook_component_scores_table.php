<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gradebook_component_scores', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('component_id');
            $table->ulid('student_id');
            // Direct staff-entered percentage override (0–100).
            $table->float('score');
            $table->ulid('updated_by_id')->nullable();
            $table->timestamps();

            $table->unique(['component_id', 'student_id']);

            $table->foreign('component_id')
                ->references('id')->on('gradebook_components')
                ->cascadeOnDelete();
            $table->foreign('student_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            $table->foreign('updated_by_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gradebook_component_scores');
    }
};
