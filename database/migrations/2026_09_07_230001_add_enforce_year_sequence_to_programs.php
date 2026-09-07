<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            // Placed next to the other graduation-rule fields (standing overrides).
            // When FALSE (default) a year-level gap emits a warning only.
            // When TRUE a year-level gap is a hard block that requires an admin override.
            $table->boolean('enforce_year_sequence')->nullable()->default(false)->after('standing_suspension_below');
        });
    }
};
