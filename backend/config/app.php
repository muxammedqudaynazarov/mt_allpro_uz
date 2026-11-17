<?php

return [
    'name' => env('APP_NAME', 'Masofaviy taʼlim tizimi'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    // React ilova manzili. Odatda backend bilan bir domen (nginx /api ni Laravel'ga yo'naltiradi).
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    'timezone' => env('APP_TIMEZONE', 'Asia/Tashkent'),
    // Asosiy til — qoraqalpoqcha (lotin). Foydalanuvchi interfeysda o'zbekchaga o'tishi mumkin.
    'locale' => env('APP_LOCALE', 'kaa'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'uz'),
    'supported_locales' => ['kaa', 'uz'],
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
