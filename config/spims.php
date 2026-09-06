<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', false),

    'seed_sample_data' => (bool) env('SEED_SAMPLE_DATA', true),

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', true),

    /*
     * Public /demo console: persona enter + DemoDataSeeder refresh.
     * On for production trial traffic by default. Set DEMO_CONSOLE=false to hide it.
     */
    'demo_console' => (bool) env('DEMO_CONSOLE', true),

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    ],
];
