<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoom bulutidagi video yozuvlar: serverga yuklab olinadi, hajmi tekshiriladi va
 * Zoom'dan o'chiriladi (10 GB bulut xotirasi yangi darslar uchun bo'shaydi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // Uchrashuv avtomatik bulutga yozish bilan yaratilganmi.
            $table->boolean('record_cloud')->default(false)->after('passcode');
            $table->timestamp('recordings_checked_at')->nullable()->after('attendance_error');
        });

        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zoom_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zoom_meeting_id', 32);
            $table->string('zoom_meeting_uuid', 128)->index();
            $table->string('zoom_file_id', 128)->unique();
            // MP4, M4A, CHAT, TRANSCRIPT, CC, TIMELINE ...
            $table->string('file_type', 24);
            $table->string('file_extension', 16)->nullable();
            $table->string('recording_type', 64)->nullable();
            $table->timestamp('recording_start')->nullable();
            $table->timestamp('recording_end')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->text('download_url')->nullable();
            // pending — yuklanishi kerak, skipped — keraksiz tur, downloaded — serverda, failed — xato.
            $table->string('status', 16)->default('pending')->index();
            $table->string('path')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('zoom_deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['record_cloud', 'recordings_checked_at']);
        });
    }
};
