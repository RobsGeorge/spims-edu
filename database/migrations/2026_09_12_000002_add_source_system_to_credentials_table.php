<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L8 — legacy credentials. See docs/legacy-data-import-plan.md §4.2, §13 (L8 row), §22.
 *
 * One concept, repeated across this whole feature: `source_system` nullable string,
 * null means native SPIMS-issued. A legacy credential's own serial (a Populi
 * certificate number, say) is stored verbatim in the existing `serial` column — it
 * never passes through `CredentialService::nextSerial()`, so it can never collide
 * with or consume from that counter. `credentials.serial` is already globally unique
 * (see the original credentials migration), which is what actually prevents a
 * collision at the database level; this column is what lets `/verify` and
 * `CredentialService::regenerate()` tell a historical record apart from one SPIMS
 * itself issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('revoked_at');
            $table->index('source_system');
        });
    }

    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropIndex(['source_system']);
            $table->dropColumn('source_system');
        });
    }
};
