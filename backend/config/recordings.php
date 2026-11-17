<?php

return [
    // Yuklab olingan Zoom video yozuvlari saqlanadigan papka (serverda yetarli joy bo'lishi kerak).
    'path' => env('RECORDINGS_PATH', storage_path('app/recordings')),

    // Diskda shundan kam joy qolsa, yangi yozuvlar yuklanmaydi (GB).
    'min_free_gb' => (int) env('RECORDINGS_MIN_FREE_GB', 5),

    // Bitta faylni yuklashga urinishlar soni.
    'max_attempts' => 5,

    // Webhook kelmasa — dars tugaganidan keyin shuncha kun ichida Zoom'dan so'rab turiladi.
    'poll_days' => 7,
];
