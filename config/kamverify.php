<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded account passwords
    |--------------------------------------------------------------------------
    |
    | Passwords for the accounts DatabaseSeeder creates. Required in
    | production — seeding aborts if unset. Read via config so they still
    | resolve when the production config cache is built (env() alone would
    | return null once bootstrap/cache/config.php exists).
    |
    */

    'seed_passwords' => [
        'admin' => env('ADMIN_SEED_PASSWORD'),
        'customer' => env('CUSTOMER_SEED_PASSWORD'),
    ],

    /*
    | Inbox that receives admin activity emails (deposits, orders,
    | refunds, support tickets, registrations). Unset = disabled.
    */

    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL'),

];
