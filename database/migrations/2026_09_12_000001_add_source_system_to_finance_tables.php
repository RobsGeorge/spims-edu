<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L6 — finance opening balances. See docs/legacy-data-import-plan.md §4.2 / §8.
 *
 * One concept, repeated: null means native SPIMS. `invoices` is a hot-path table for
 * dunning/gateway exclusion so it is indexed; `payments` gets the column for schema
 * completeness (per §4.2's table) but has no importer writing to it yet under D4 (no
 * historical payments are imported), so it is not indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('status');
            $table->index('source_system');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('source_system')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('source_system');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['source_system']);
            $table->dropColumn('source_system');
        });
    }
};
