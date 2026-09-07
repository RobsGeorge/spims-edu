<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_programs', function (Blueprint $table) {
            $table->string('academic_standing')->nullable()->after('cached_gpa');
            $table->index('academic_standing');
        });
    }
};
