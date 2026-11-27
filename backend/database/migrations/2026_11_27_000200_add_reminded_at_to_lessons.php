<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kalendardagi dars haqida o'qituvchiga boshlanishidan 30 daqiqa oldin eslatma yuborilgan vaqt. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('is_lecture');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
    }
};
