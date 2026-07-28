<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->boolean('is_rtl')->default(false);
            $table->boolean('enabled')->default(true);
        });

        Schema::create('translations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('entity_type');
            $table->ulid('entity_id');
            $table->string('field');
            $table->string('locale', 10);
            $table->text('value');
            $table->string('source')->default('HUMAN');
            $table->boolean('verified')->default(false);
            $table->foreignUlid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['entity_type', 'entity_id', 'field', 'locale']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
        Schema::dropIfExists('languages');
    }
};
