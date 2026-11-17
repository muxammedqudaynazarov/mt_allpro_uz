<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Har haftalik takroriy darslar. Zoom uchrashuvi oldindan yaratilmaydi: dars kuni,
 * boshlanishidan 1 soat oldin tizim bo'sh hostda uchrashuv ochadi va havolani
 * o'qituvchiga Telegram orqali yuboradi. O'qituvchi bitta sanani (skip) yoki
 * butun jadvalni bekor qilishi mumkin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_slot_id')->constrained()->restrictOnDelete();
            // Afzal ko'rilgan host: o'sha kuni band bo'lsa, boshqa bo'sh host olinadi.
            $table->foreignId('zoom_host_id')->nullable()->constrained()->nullOnDelete();
            // ISO hafta kuni: 1 — dushanba ... 7 — yakshanba.
            $table->unsignedTinyInteger('weekday');
            $table->string('topic', 200)->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_id')->nullable()->constrained('users')->nullOnDelete();
            // Oxirgi muvaffaqiyatsiz urinish: o'qituvchiga bir kunda bir marta xabar beriladi.
            $table->string('last_error', 255)->nullable();
            $table->date('last_error_on')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'weekday']);
        });

        Schema::create('lesson_series_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_series_id')->constrained('lesson_series')->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['lesson_series_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_series_skips');
        Schema::dropIfExists('lesson_series');
    }
};
