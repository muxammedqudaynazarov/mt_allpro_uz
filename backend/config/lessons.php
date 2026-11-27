<?php

return [
    'university' => env('APP_UNIVERSITY', 'Qoraqalpoq davlat universiteti'),

    // "Hozir" rejimida yaratilgan darsning davomiyligi (daqiqa). Bir para — 80 daqiqa.
    'default_duration' => (int) env('LESSON_DEFAULT_DURATION', 80),

    // Dam olish kunlari (ISO: 1 — dushanba ... 7 — yakshanba): bu kunlarga dars yaratilmaydi,
    // haftalik dars qo'shilmaydi, mavjud haftalik jadvallar ham shu kunlari o'tkazib yuboriladi.
    'closed_weekdays' => [7],

    // Kalendardagi (haftalik yoki oldindan rejalashtirilgan) dars haqida o'qituvchiga Telegram
    // eslatmasi — boshlanishidan shuncha daqiqa oldin. 0 — yuborilmaydi.
    'reminder_minutes' => (int) env('LESSON_REMINDER_MINUTES', 30),

    // Bir guruh yoki host uchun parallel dars yaratishda qulfni kutish, soniya.
    'lock_wait' => 10,
];
