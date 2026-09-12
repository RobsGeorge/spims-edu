<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rung 4 of the identity matching ladder (docs/legacy-data-import-plan.md §6): a legacy
 * student number, when both the incoming row and an existing user carry one and it
 * matches unambiguously, auto-links instead of creating a duplicate. Nullable — native
 * SPIMS users never had one — and indexed for the ladder's lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_number')->nullable()->after('source_system');
            $table->index('student_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['student_number']);
            $table->dropColumn('student_number');
        });
    }
};
