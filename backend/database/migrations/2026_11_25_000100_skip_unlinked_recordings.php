<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tizim darsiga tegishli bo'lmagan Zoom uchrashuvlari (masalan, shu akkaunt bilan o'tkazilgan
 * seminar) endi yuklab olinmaydi. Navbatda yoki xato bilan qolganlari to'xtatiladi; serverga
 * tushib bo'lganlarini Administrator "Video yozuvlar" sahifasida o'chiradi.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('recordings')->whereNull('lesson_id')->whereIn('status', ['pending', 'failed'])->update(['status' => 'skipped', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Ma'lumotni qaytarish shart emas.
    }
};
