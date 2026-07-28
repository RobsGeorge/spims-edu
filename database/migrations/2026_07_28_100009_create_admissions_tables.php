<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_forms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
        });

        Schema::create('application_form_fields', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained('application_forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('type');
            $table->boolean('required')->default(false);
            $table->unsignedInteger('order');
            $table->json('options')->nullable();
            $table->json('allowed_file_types')->nullable();
            $table->text('admin_note')->nullable();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('applicant_id')->constrained('users');
            $table->foreignUlid('program_id')->constrained('programs');
            $table->foreignUlid('form_id')->constrained('application_forms');
            $table->string('status')->default('DRAFT');
            $table->foreignUlid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_id', 'program_id']);
            $table->index('status');
        });

        Schema::create('application_field_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignUlid('field_id')->constrained('application_form_fields');
            $table->text('value')->nullable();
            $table->string('file_url')->nullable();

            $table->unique(['application_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_field_values');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('application_form_fields');
        Schema::dropIfExists('application_forms');
    }
};
