<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('role');
            $table->string('permission_key');
            $table->string('level');
            $table->timestamps();

            $table->unique(['role', 'permission_key']);
            $table->index('permission_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
