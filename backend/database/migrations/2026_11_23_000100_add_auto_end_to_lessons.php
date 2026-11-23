<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dars yaratilganda Zoom'ni avtomatik yakunlash yoqilgan bo'lsa — vaqti kelganda Zoom yopiladi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('auto_end')->default(false)->after('record_cloud');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('auto_end');
        });
    }
};
