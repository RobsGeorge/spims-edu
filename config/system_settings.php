<?php

/**
 * Allowlisted school knobs stored in `settings`. Super Admin edits these via
 * system_settings.manage. Secrets never appear here.
 */
return [
    'editable' => [
        'school.default_locale' => [
            'type' => 'locale',
            'default' => 'en',
            'options' => ['ar', 'en', 'fr'],
        ],
        'school.timezone' => [
            'type' => 'timezone',
            'default' => 'UTC',
        ],
        'attendance.default_threshold' => [
            'type' => 'int',
            'default' => 60,
            'min' => 0,
            'max' => 100,
        ],
        'late_penalty.escalating' => [
            'type' => 'int_list',
            'default' => [0, 10, 20, 30],
        ],
        'zoom.concurrent_hosts' => [
            'type' => 'int',
            'default' => 1,
            'min' => 1,
            'max' => 20,
        ],
        'audit.retention_days' => [
            'type' => 'int',
            'default' => 365,
            'min' => 1,
            'max' => 3650,
        ],
        'backup.retention_days' => [
            'type' => 'int',
            'default' => 14,
            'min' => 1,
            'max' => 3650,
        ],
        'mail.from_name' => [
            'type' => 'string',
            'default' => 'SPIMS',
            'max' => 120,
        ],
    ],

    /*
     * Read-only integration probes. Values are never passed to views —
     * only whether the env slot is filled.
     */
    'integrations' => [
        'mail_password' => ['env' => 'MAIL_PASSWORD'],
        'mail_username' => ['env' => 'MAIL_USERNAME'],
        'paypal' => ['env' => 'PAYPAL_SECRET'],
        'paymob' => ['env' => 'PAYMOB_API_KEY'],
        'zoom' => ['env' => 'ZOOM_CLIENT_SECRET'],
        'vimeo' => ['env' => 'VIMEO_TOKEN'],
        'gemini' => ['env' => 'GOOGLE_API_KEY'],
        'superadmin_email' => ['env' => 'SUPERADMIN_EMAIL'],
        'superadmin_password' => ['env' => 'SUPERADMIN_PASSWORD'],
    ],
];
