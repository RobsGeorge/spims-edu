<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('help_articles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')->constrained('help_categories')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_public')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignUlid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'category_id']);
            $table->index(['is_public', 'status']);
        });

        Schema::create('help_article_locales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->string('summary')->nullable();
            $table->text('body_markdown');
            $table->timestamps();

            $table->unique(['article_id', 'locale']);
        });

        Schema::create('help_article_audiences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->string('role', 64);
            $table->timestamps();

            $table->unique(['article_id', 'role']);
        });

        Schema::create('help_media', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('article_id')->constrained('help_articles')->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_media');
        Schema::dropIfExists('help_article_audiences');
        Schema::dropIfExists('help_article_locales');
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('help_categories');
    }
};
