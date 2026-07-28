<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('password_hash')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->string('preferred_locale', 10)->default('en');
            $table->string('theme_preference')->default('SYSTEM');
            $table->string('country_code', 10)->nullable();
            $table->string('status')->default('PENDING');
            $table->boolean('is_reviewer')->default(false);
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role');

            $table->unique(['user_id', 'role']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('otp_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
    }
};
