<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'attendance.default_threshold' => ['value' => 60],
            'late_penalty.escalating' => ['value' => [0, 10, 20, 30]],
            'zoom.concurrent_hosts' => ['value' => 1],
        ];

        foreach ($defaults as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
