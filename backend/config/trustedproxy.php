<?php

/*
 * Ishonchli proksilar. Laravel TrustProxies bu qiymatni har so'rovda konfiguratsiyadan
 * o'qiydi — `php artisan optimize` (config kesh) dan keyin ham .env dagi qiymat ishlaydi.
 * (Avval bootstrap/app.php da env() bilan o'qilardi: config keshlanganda .env o'qilmaydi
 * va qiymat jimgina 127.0.0.1 ga qaytardi.)
 *
 * Sayt oldida boshqa serverdagi proksi bo'lsa — o'sha serverning IP manzili, vergul bilan bir nechta.
 * Proksi bo'lmasa: 127.0.0.1.
 * Noto'g'ri bo'lsa, `php artisan app:check` proksi so'rovlari kelgan haqiqiy IP'ni ko'rsatadi.
 */
return [
    'proxies' => env('TRUSTED_PROXIES', '127.0.0.1'),
];
