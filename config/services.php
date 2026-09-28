<?php

return [

    'hero_sms' => [
        'base_url' => env('HERO_SMS_BASE_URL', 'https://hero-sms.com/stubs/handler_api.php'),
        'api_key' => env('HERO_SMS_API_KEY'),
        'mode' => env('HERO_SMS_MODE'),
        'use_mock' => env('HERO_SMS_USE_MOCK', true),
        'timeout' => env('HERO_SMS_TIMEOUT', 30),
        'retry_attempts' => env('HERO_SMS_RETRY_ATTEMPTS', 3),
        'reseller_enabled' => env('HERO_SMS_RESELLER_ENABLED', false),
        'reseller_param' => env('HERO_SMS_RESELLER_PARAM', 'userId'),
    ],

    'fapshi' => [
        // sandbox | live — maps to sandbox.fapshi.com / live.fapshi.com
        'mode' => env('FAPSHI_MODE', 'sandbox'),
        'api_user' => env('FAPSHI_API_USER'),
        'api_key' => env('FAPSHI_API_KEY'),
        'use_mock' => env('FAPSHI_USE_MOCK', true),
        'timeout' => env('FAPSHI_TIMEOUT', 30),
    ],

    'webpush' => [
        // VAPID keys for browser push notifications.
        // Generate: php artisan tinker --execute="var_export(\Minishlink\WebPush\VAPID::createVapidKeys())"
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

];