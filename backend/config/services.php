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
        // Faqat kerakli maydonlar: F.I.Sh., HEMIS ID va rasm saqlanadi; type va university_id —
        // kirish huquqini tekshirish uchun, login — eski tizimdan ko'chirilgan hisobni topish uchun (saqlanmaydi).
        // Talabalar ma'lumoti (HEMIS REST API, student.karsu.uz/rest/docs): faqat F.I.Sh. va rasm olinadi.
        'api_url' => env('HEMIS_API_URL', 'https://student.karsu.uz/rest'),
        'api_token' => env('HEMIS_API_TOKEN'),
        'student_endpoint' => env('HEMIS_STUDENT_ENDPOINT', 'v1/data/student-list'),
        'student_search_param' => env('HEMIS_STUDENT_SEARCH_PARAM', 'search'),
        'user_fields' => env('HEMIS_USER_FIELDS', 'id,type,name,firstname,surname,patronymic,picture,login,university_id'),
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
