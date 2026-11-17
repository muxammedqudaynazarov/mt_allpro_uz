<?php

use Laravel\Sanctum\Sanctum;

/*
 * Frontend va backend bitta domenda ishlaydi, shuning uchun sayt ochilgan domenning
 * o'zi (joriy so'rov hosti) doim "stateful" hisoblanadi: brauzer Origin/Referer
 * sarlavhasi so'rov hostiga teng bo'lsa, cookie-sessiya va CSRF himoyasi yoqiladi.
 * Shunda .env da domen eskicha yozilib qolgan bo'lsa ham kirish ishlaydi.
 *
 * SANCTUM_STATEFUL_DOMAINS — faqat qo'shimcha domenlar (masalan, lokal ishlab chiqish).
 * Qolgan Sanctum sozlamalari paketdagi standart qiymatlarda qoladi.
 */
return [
    'stateful' => array_values(array_unique(array_filter(array_map('trim', [
        ...explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173,127.0.0.1,127.0.0.1:5173,127.0.0.1:4173')),
        Sanctum::$currentRequestHostPlaceholder,
    ])))),
];
