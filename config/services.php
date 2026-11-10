<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    // Correos transaccionales (Resend, vía su API REST). Sin llave, simplemente no se envían correos.
    'resend' => [
        'key'  => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM', 'OficiosYa <onboarding@resend.dev>'),
    ],

    // Pagos (Mercado Pago, Checkout Pro). Solo se usa con MARKETPLACE_PASARELA=mercadopago.
    'mercadopago' => [
        'access_token'     => env('MP_ACCESS_TOKEN'),
        'webhook_secret'   => env('MP_WEBHOOK_SECRET'),   // opcional: firma de las notificaciones
        // false (recomendado): se usa init_point. Mercado Pago indica que sandbox_init_point suele dar
        // el error "Oh, no, algo anduvo mal"; las pruebas de Checkout Pro se hacen con cuentas de prueba.
        'sandbox'          => (bool) env('MP_SANDBOX', false),
        'notification_url' => env('MP_NOTIFICATION_URL'), // URL pública https (túnel); opcional en local
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

    // Notificaciones push (Web Push + VAPID). Genera las llaves con: php artisan push:generar-vapid
    'webpush' => [
        'subject'     => env('VAPID_SUBJECT', 'mailto:admin@oficiosya.test'),
        'public_key'  => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        // Anti-SSRF: solo se envía a estos servicios de push (y sus subdominios).
        'hosts_permitidos' => [
            'fcm.googleapis.com',          // Chrome, Brave, Edge en Android, Samsung
            'push.services.mozilla.com',   // Firefox
            'push.apple.com',              // Safari / iOS (web.push.apple.com)
            'notify.windows.com',          // Edge / Windows
        ],
    ],

];
