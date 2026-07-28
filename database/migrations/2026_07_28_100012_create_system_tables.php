<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->ulid('entity_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->foreignUlid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('themes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->string('site_name')->default('Spims');
            $table->string('logo_light_url')->nullable();
            $table->string('logo_dark_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->json('tokens');
            $table->foreignUlid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('student_id')->constrained('users');
            $table->string('type');
            $table->foreignUlid('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignUlid('offering_id')->nullable()->constrained('course_offerings')->nullOnDelete();
            $table->string('serial')->unique();
            $table->string('qr_token')->unique();
            $table->string('language', 10)->default('en');
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->string('file_url')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
    }
};
