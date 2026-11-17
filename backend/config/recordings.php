<?php

return [
    // Yuklab olingan Zoom video yozuvlari saqlanadigan papka (serverda yetarli joy bo'lishi kerak).
    'path' => env('RECORDINGS_PATH', storage_path('app/recordings')),

    // Diskda shundan kam joy qolsa, yangi yozuvlar yuklanmaydi (GB).
    'min_free_gb' => (int) env('RECORDINGS_MIN_FREE_GB', 5),

    // nginx X-Accel-Redirect: PHP faqat ruxsatni tekshiradi, faylni nginx beradi (katta
    // videolar PHP jarayonini band qilmaydi). nginx'dagi "internal" location prefiksi,
    // masalan /_zoom_recordings/. Bo'sh bo'lsa — fayl PHP orqali beriladi.
    'accel_prefix' => env('RECORDINGS_ACCEL_PREFIX', ''),

    // Bitta faylni yuklashga urinishlar soni.
    'max_attempts' => 5,

    // Webhook kelmasa — dars tugaganidan keyin shuncha kun ichida Zoom'dan so'rab turiladi.
    'poll_days' => 7,
];
