<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->timestamp('registration_start');
            $table->timestamp('registration_end');
            $table->unsignedInteger('add_drop_end_week');
            $table->unsignedInteger('last_withdrawal_week');
            $table->float('withdrawal_refund_percent')->default(0);
            $table->string('status')->default('DRAFT');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('academic_years');
    }
};
