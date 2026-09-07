<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', false),

    'seed_sample_data' => (bool) env('SEED_SAMPLE_DATA', true),

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', true),

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    ],

    'audit' => [
        'retention_days' => 365,
        'export_cap' => 10000,
        'protected_prefixes' => [
            'users.impersonate.',
            'roles.hub.',
            'rbac.',
            'features.',
            'system_settings.',
        ],
    ],
];
