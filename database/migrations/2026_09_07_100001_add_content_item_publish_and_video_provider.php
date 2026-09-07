<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->boolean('published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->string('video_provider', 16)->nullable();
        });

        DB::table('content_items')
            ->whereNotNull('vimeo_id')
            ->where('vimeo_id', '!=', '')
            ->whereNull('video_provider')
            ->update(['video_provider' => 'VIMEO']);

        DB::table('content_items')
            ->where('published', true)
            ->whereNull('published_at')
            ->update(['published_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table) {
            $table->dropColumn(['published', 'published_at', 'video_provider']);
        });
    }
};
