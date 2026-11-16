<?php

return [
    'university' => env('APP_UNIVERSITY', 'Qoraqalpoq davlat universiteti'),

    // "Hozir" rejimida yaratilgan darsning davomiyligi (daqiqa). Bir para — 80 daqiqa.
    'default_duration' => (int) env('LESSON_DEFAULT_DURATION', 80),
];
