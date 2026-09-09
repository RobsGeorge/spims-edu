<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('level')->nullable()->after('type');
            $table->boolean('require_all_courses_to_graduate')->default(false)->after('passing_threshold');
            $table->string('certificate_template')->nullable()->after('signatory_title');
            $table->boolean('issue_credential_on_completion')->default(false)->after('certificate_template');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'level',
                'require_all_courses_to_graduate',
                'certificate_template',
                'issue_credential_on_completion',
            ]);
        });
    }
};
