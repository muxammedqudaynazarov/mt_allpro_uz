<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoom "recording.completed" webhook'idagi download_token: shu uchrashuv yozuvlarini yuklash uchun
 * Zoom bergan token (24 soat amal qiladi). Ilova ruxsatlariga bog'liq emas — yuklashning birinchi usuli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->text('download_token')->nullable()->after('download_url');
            $table->timestamp('download_token_expires_at')->nullable()->after('download_token');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['download_token', 'download_token_expires_at']);
        });
    }
};
