<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'system_docs.guest_published'],
            [
                'value' => [
                    'enabled' => true,
                    'updated_at' => now()->toIso8601String(),
                ],
            ]
        );
    }

    public function down(): void
    {
        Setting::query()->where('key', 'system_docs.guest_published')->delete();
    }
};
