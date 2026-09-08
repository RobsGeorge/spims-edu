<?php

/**
 * Allowlisted integration knobs stored in `settings`. Super Admin edits these
 * via integrations.manage. Secrets are write-once (encrypted) and never
 * returned to views. Host .env remains the fallback and is never written.
 */
return [
    'identities' => ['transactional', 'notifications'],

    'gateways' => ['PAYPAL', 'PAYMOB', 'CASHIER'],

    'safe' => [
        'integrations.mail.mailer' => [
            'type' => 'select',
            'group' => 'mail_transport',
            'options' => ['log', 'smtp', 'array', 'failover'],
            'nullable' => true,
            'env' => 'MAIL_MAILER',
        ],
        'integrations.mail.host' => [
            'type' => 'string',
            'group' => 'mail_transport',
            'max' => 255,
            'nullable' => true,
            'env' => 'MAIL_HOST',
        ],
        'integrations.mail.port' => [
            'type' => 'int',
            'group' => 'mail_transport',
            'min' => 1,
            'max' => 65535,
            'nullable' => true,
            'env' => 'MAIL_PORT',
        ],
        'integrations.mail.encryption' => [
            'type' => 'select',
            'group' => 'mail_transport',
            'options' => ['tls', 'ssl'],
            'nullable' => true,
            'env' => 'MAIL_ENCRYPTION',
        ],
        'integrations.mail.username' => [
            'type' => 'string',
            'group' => 'mail_transport',
            'max' => 255,
            'nullable' => true,
            'env' => 'MAIL_USERNAME',
        ],
        'integrations.mail.transactional.from_address' => [
            'type' => 'email',
            'group' => 'mail_transactional',
            'nullable' => true,
            'env' => 'MAIL_FROM_ADDRESS',
        ],
        'integrations.mail.transactional.from_name' => [
            'type' => 'string',
            'group' => 'mail_transactional',
            'max' => 120,
            'nullable' => true,
            'env' => 'MAIL_FROM_NAME',
        ],
        'integrations.mail.notifications.from_address' => [
            'type' => 'email',
            'group' => 'mail_notifications',
            'nullable' => true,
        ],
        'integrations.mail.notifications.from_name' => [
            'type' => 'string',
            'group' => 'mail_notifications',
            'max' => 120,
            'nullable' => true,
        ],
        'integrations.paypal.enabled' => [
            'type' => 'bool',
            'group' => 'paypal',
            'default' => true,
        ],
        'integrations.paypal.mode' => [
            'type' => 'select',
            'group' => 'paypal',
            'options' => ['sandbox', 'live'],
            'default' => 'sandbox',
        ],
        'integrations.paypal.client_id' => [
            'type' => 'string',
            'group' => 'paypal',
            'max' => 255,
            'nullable' => true,
            'env' => 'PAYPAL_CLIENT_ID',
        ],
        'integrations.paymob.enabled' => [
            'type' => 'bool',
            'group' => 'paymob',
            'default' => true,
        ],
        'integrations.paymob.integration_id' => [
            'type' => 'string',
            'group' => 'paymob',
            'max' => 64,
            'nullable' => true,
            'env' => 'PAYMOB_INTEGRATION_ID',
        ],
        'integrations.cashier.enabled' => [
            'type' => 'bool',
            'group' => 'cashier',
            'default' => true,
        ],
    ],

    'secrets' => [
        'integrations.mail.password' => [
            'group' => 'mail_transport',
            'env' => 'MAIL_PASSWORD',
        ],
        'integrations.paypal.secret' => [
            'group' => 'paypal',
            'env' => 'PAYPAL_SECRET',
        ],
        'integrations.paypal.webhook_id' => [
            'group' => 'paypal',
            'env' => 'PAYPAL_WEBHOOK_ID',
        ],
        'integrations.paymob.api_key' => [
            'group' => 'paymob',
            'env' => 'PAYMOB_API_KEY',
        ],
        'integrations.paymob.hmac' => [
            'group' => 'paymob',
            'env' => 'PAYMOB_HMAC',
        ],
        'integrations.cashier.secret' => [
            'group' => 'cashier',
            'env' => 'CASHIER_SECRET',
        ],
    ],
];
