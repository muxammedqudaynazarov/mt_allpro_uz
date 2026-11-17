<?php

return [

    /*
    | HEMIS OAuth (xodimlar uchun). HEMIS administratori tizimni OAuth mijozi
    | sifatida ro'yxatdan o'tkazadi va Client ID / Secret beradi.
    | Redirect URI: https://<domen>/auth/hemis/callback
    */
    'hemis' => [
        'base_url' => env('HEMIS_BASE_URL'),
        'client_id' => env('HEMIS_CLIENT_ID'),
        'client_secret' => env('HEMIS_CLIENT_SECRET'),
        'redirect_uri' => env('HEMIS_REDIRECT_URI'),
        // Bo'sh bo'lsa tekshirilmaydi. Boshqa OTM xodimlarining kirishini to'sish uchun to'ldiring.
        'university_id' => env('HEMIS_UNIVERSITY_ID'),
        'allowed_types' => env('HEMIS_ALLOWED_TYPES', 'employee'),
        // true: yangi xodim "tasdiq kutmoqda" holatida yaratiladi; false: faqat oldindan qo'shilganlar kira oladi.
        'auto_register' => env('HEMIS_AUTO_REGISTER', true),
        // Faqat tizimga kerakli maydonlar: telefon, email, rasm, pasport va JSHSHIR so'ralmaydi.
        'user_fields' => env('HEMIS_USER_FIELDS', 'id,uuid,type,name,firstname,surname,patronymic,login,university_id'),
    ],

    'zoom' => [
        'api_url' => env('ZOOM_API_URL', 'https://api.zoom.us/v2'),
        'oauth_url' => env('ZOOM_OAUTH_URL', 'https://zoom.us/oauth/token'),
        'timeout' => (int) env('ZOOM_TIMEOUT', 15),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),
    ],

];
