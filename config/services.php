<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'whatsapp' => [
        'number' => env('WA_NUMBER', '628219207240'),
        'display_number' => env('WA_DISPLAY_NUMBER', '08219207240'),
        'gateway_url' => env('WHATSAPP_GATEWAY_URL', 'http://whatsapp:3000'),
        'internal_token' => env('WHATSAPP_INTERNAL_TOKEN'),
        'timeout' => (int) env('WHATSAPP_GATEWAY_TIMEOUT', 10),
    ],

    'discord' => [
        'enabled' => (bool) env('DISCORD_NOTIFY_ENABLED', false),
        'webhook_url' => env('DISCORD_BOT_WEBHOOK_URL'),
        'token' => env('DISCORD_BOT_WEBHOOK_TOKEN'),
        'timeout' => (int) env('DISCORD_BOT_TIMEOUT', 8),
    ],

    'store' => [
        'address' => env('STORE_ADDRESS', 'JL. Kapten Mulyono No. 60C'),
        'open_hours' => env('STORE_OPEN_HOURS', 'Senin - Sabtu 09:00 - 17:00'),
        'maps_url' => env('STORE_MAPS_URL', 'https://maps.google.com/?q=JL.+Kapten+Mulyono+No.+60C'),
        'maps_embed_url' => env('STORE_MAPS_EMBED_URL', 'https://www.google.com/maps?q=JL.+Kapten+Mulyono+No.+60C&output=embed'),
    ],

    'firebase' => [
        'enabled' => (bool) env('FIREBASE_AUTH_ENABLED', false),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'web' => [
            'apiKey' => env('FIREBASE_API_KEY'),
            'authDomain' => env('FIREBASE_AUTH_DOMAIN'),
            'projectId' => env('FIREBASE_PROJECT_ID'),
            'storageBucket' => env('FIREBASE_STORAGE_BUCKET'),
            'messagingSenderId' => env('FIREBASE_MESSAGING_SENDER_ID'),
            'appId' => env('FIREBASE_APP_ID'),
            'measurementId' => env('FIREBASE_MEASUREMENT_ID'),
        ],
    ],

    'file_security' => [
        'antivirus_driver' => env('ANTIVIRUS_DRIVER', 'clamav'),
        'antivirus_binary' => env('ANTIVIRUS_BINARY'),
        'antivirus_required' => (bool) env('ANTIVIRUS_REQUIRED', env('APP_ENV') === 'production'),
        'scan_timeout' => (int) env('ANTIVIRUS_SCAN_TIMEOUT', 30),
        'retention_days' => (int) env('SERVICE_FILE_RETENTION_DAYS', 90),
    ],

    'orders' => [
        'max_revision_requests' => (int) env('SERVICE_ORDER_MAX_REVISIONS', 3),
    ],

    'security' => [
        'alert_email' => env('SECURITY_ALERT_EMAIL', env('MAIL_FROM_ADDRESS')),
    ],

    'backup_r2' => [
        'enabled' => (bool) env('BACKUP_R2_ENABLED', false),
        'key' => env('R2_ACCESS_KEY_ID'),
        'secret' => env('R2_SECRET_ACCESS_KEY'),
        'bucket' => env('R2_BUCKET'),
        'endpoint' => env('R2_ENDPOINT'),
        'region' => env('R2_REGION', 'auto'),
    ],

    'google_drive_backup' => [
        'enabled' => (bool) env('GOOGLE_DRIVE_BACKUP_ENABLED', false),
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
        'folder_name' => env('GOOGLE_DRIVE_FOLDER_NAME', 'Younz Digital Center Backups'),
        'timeout' => (int) env('GOOGLE_DRIVE_TIMEOUT', 120),
    ],

    'digiflazz' => [
        'enabled' => (bool) env('DIGIFLAZZ_ENABLED', false),
        'username' => env('DIGIFLAZZ_USERNAME'),
        'api_key' => env('DIGIFLAZZ_API_KEY'),
        'testing' => (bool) env('DIGIFLAZZ_TESTING', true),
        'endpoint' => env('DIGIFLAZZ_ENDPOINT', 'https://api.digiflazz.com/v1'),
        'webhook_secret' => env('DIGIFLAZZ_WEBHOOK_SECRET'),
        'prepaid_markup' => (int) env('DIGIFLAZZ_PREPAID_MARKUP', 2000),
        'prepaid_high_value_threshold' => (int) env('DIGIFLAZZ_PREPAID_HIGH_VALUE_THRESHOLD', 200000),
        'prepaid_high_value_markup' => (int) env('DIGIFLAZZ_PREPAID_HIGH_VALUE_MARKUP', 5000),
        'prepaid_rounding_unit' => (int) env('DIGIFLAZZ_PREPAID_ROUNDING_UNIT', 1000),
        'prepaid_price_overrides' => env('DIGIFLAZZ_PREPAID_PRICE_OVERRIDES', ''),
        'postpaid_admin_fee' => (int) env('DIGIFLAZZ_POSTPAID_ADMIN_FEE', 5500),
    ],

    'midtrans' => [
        'enabled' => (bool) env('MIDTRANS_ENABLED', false),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'production' => (bool) env('MIDTRANS_PRODUCTION', false),
        'expiry_minutes' => (int) env('MIDTRANS_EXPIRY_MINUTES', 60),
        'notification_url' => env('MIDTRANS_NOTIFICATION_URL'),
    ],

    'topup' => [
        'access_link_days' => (int) env('TOPUP_ACCESS_LINK_DAYS', 7),
    ],

    // Server-to-server read-only bridge used by the YOUNZ ERP API. The
    // token is never sent to the browser or Android application.
    'younz_erp' => [
        'sync_token' => env('YOUNZ_ERP_SYNC_TOKEN'),
    ],

    'younz_ppob' => [
        'agent_token' => env('YOUNZ_PPOB_AGENT_TOKEN'),
        'operator_token' => env('YOUNZ_PPOB_OPERATOR_TOKEN'),
        // Direct WhatsApp execution is enabled only when both values are set.
        // Store an Argon2id/Bcrypt hash here, never the plaintext confirmation code.
        'whatsapp_confirmation_hash' => env('YOUNZ_PPOB_WHATSAPP_CONFIRMATION_HASH'),
        'whatsapp_operator_numbers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('YOUNZ_PPOB_WHATSAPP_OPERATOR_NUMBERS', '')),
        ))),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
