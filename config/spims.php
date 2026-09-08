<?php

return [
    'force_https' => (bool) env('FORCE_HTTPS', false),

    'seed_sample_data' => (bool) env('SEED_SAMPLE_DATA', true),

    'seed_demo_data' => (bool) env('SEED_DEMO_DATA', true),

    'backup' => [
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    ],

    'content' => [
        'video_providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SPIMS_VIDEO_PROVIDERS', 'VIMEO,YOUTUBE'))
        ))),
        'reading_embed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'SPIMS_READING_EMBED_HOSTS',
                'drive.google.com,docs.google.com,www.dropbox.com,dl.dropboxusercontent.com,onedrive.live.com,1drv.ms'
            ))
        ))),
        'allow_unknown_reading_urls' => filter_var(
            env('SPIMS_ALLOW_UNKNOWN_READING_URLS', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'upload_max_mb' => (int) env('SPIMS_UPLOAD_MAX_MB', 20),
        'upload_mimes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SPIMS_UPLOAD_MIMES', 'pdf,jpg,jpeg,png,webp,gif'))
        ))),
        'student_file_download' => filter_var(
            env('SPIMS_STUDENT_FILE_DOWNLOAD', true),
            FILTER_VALIDATE_BOOLEAN
        ),
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
            'integrations.',
            'ops.',
        ],
    ],
];
