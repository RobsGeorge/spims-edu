<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', false),

    'seed_sample_data' => (bool) env('SEED_SAMPLE_DATA', true),

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    ],
];
