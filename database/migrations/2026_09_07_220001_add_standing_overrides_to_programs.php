<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedInteger('standing_good_min')->nullable()->after('active');
            $table->unsignedInteger('standing_suspension_below')->nullable()->after('standing_good_min');
        });
    }
};
