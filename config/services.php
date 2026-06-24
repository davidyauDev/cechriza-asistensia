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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'solicitudes' => [
        'area_compras_id' => (int) env('SOLICITUD_AREA_COMPRAS_ID', 7),
        'pedido_compra_notify_email' => env('PEDIDO_COMPRA_NOTIFY_EMAIL'),
        'gerencia_cc_emails' => env('SOLICITUD_COMPRA_GERENCIA_CC_EMAILS', ''),
        'correo_gerencia' => env('CORREO_GERENCIA', ''),
        'correo_logistica' => env('CORREO_LOGISTICA'),
        'correo_soma' => env('CORREO_SOMA'),
        'comprobante_gasto_notify_to' => env('COMPROBANTE_GASTO_NOTIFY_TO', ''),
        'comprobante_gasto_notify_cc_operaciones' => env('COMPROBANTE_GASTO_NOTIFY_CC_OPERACIONES', ''),
        'comprobante_gasto_notify_cc_ssoma' => env('COMPROBANTE_GASTO_NOTIFY_CC_SSOMA', ''),
        'smtp_always_cc' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SMTP_ALWAYS_CC', ''))
        ))),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH'),
    ],

    'external_api' => [
        'key' => env('EXTERNAL_API_KEY', ''),
        'secret' => env('EXTERNAL_API_SECRET', ''),
        'signature_ttl' => (int) env('EXTERNAL_API_SIGNATURE_TTL', 300),
    ],

];
