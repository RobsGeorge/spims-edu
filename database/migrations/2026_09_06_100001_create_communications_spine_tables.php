<?php

use App\Enums\AnnouncementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('status')->default(AnnouncementStatus::Published->value);
            $table->timestamp('published_at')->nullable();
            $table->foreignUlid('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_banner')->default(false);
            $table->timestamp('banner_expires_at')->nullable();
            $table->text('body_ar')->nullable();
            $table->text('body_fr')->nullable();
        });

        // Existing rows predate the draft/publish workflow and are already visible
        // in the course player — treat them as published so students keep seeing them.
        if (Schema::hasColumn('announcements', 'status')) {
            DB::table('announcements')->whereNull('published_at')->update([
                'status' => AnnouncementStatus::Published->value,
                'published_at' => DB::raw('created_at'),
            ]);
        }

        Schema::create('announcement_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignUlid('editor_id')->constrained('users');
            $table->string('title');
            $table->text('body');
            $table->text('body_ar')->nullable();
            $table->text('body_fr')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('target_type');
            $table->string('target_id');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target_type', 'target_id']);
            $table->unique(['announcement_id', 'target_type', 'target_id']);
        });

        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignUlid('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel');
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['announcement_id', 'recipient_id', 'channel']);
            $table->index(['recipient_id', 'status']);
        });

        Schema::create('communication_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type');
            $table->string('channel');
            $table->foreignUlid('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->string('locale', 8)->nullable();
            $table->string('status');
            $table->string('provider_message_id')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'channel', 'status']);
            $table->index(['recipient_id', 'created_at']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('scope_type')->nullable();
            $table->string('scope_id')->nullable();
            $table->string('key');
            $table->string('locale', 8);
            $table->string('subject');
            $table->text('body');
            $table->foreignUlid('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['key', 'locale']);
            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key');
            $table->string('channel');
            $table->boolean('enabled');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['user_id', 'event_key', 'channel']);
        });

        Schema::create('notification_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->timestamp('remind_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['remind_at', 'sent_at']);
            $table->index(['user_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reminders');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('communication_logs');
        Schema::dropIfExists('announcement_deliveries');
        Schema::dropIfExists('announcement_targets');
        Schema::dropIfExists('announcement_revisions');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_id');
            $table->dropColumn([
                'status',
                'published_at',
                'is_banner',
                'banner_expires_at',
                'body_ar',
                'body_fr',
            ]);
        });
    }
};
