<?php

/*
| Laravel standart ulanishlari (mysql, sqlite, ...) framework'dan avtomatik olinadi.
| Bu yerda faqat eski tizimdan ma'lumot ko'chirish uchun "legacy" ulanishi qo'shilgan
| (php artisan legacy:import).
*/

return [
    'connections' => [
        'legacy' => [
            'driver' => env('LEGACY_DB_CONNECTION', 'mysql'),
            'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB_PORT', '3306'),
            'database' => env('LEGACY_DB_DATABASE', 'mt_allpro_old'),
            'username' => env('LEGACY_DB_USERNAME', 'root'),
            'password' => env('LEGACY_DB_PASSWORD', ''),
            'unix_socket' => env('LEGACY_DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],
];
