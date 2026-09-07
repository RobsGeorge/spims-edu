<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['applicant_id', 'program_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->index(['applicant_id', 'program_id'], 'applications_applicant_program_idx');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_applicant_program_idx');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unique(['applicant_id', 'program_id']);
        });
    }
};
