<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Darsni boshlashda Zoom host akkauntining ko'rinadigan ismi o'qituvchi nomiga o'zgartiriladi
 * ("QUDAYNAZAROV M.S."). Oxirgi qo'yilgan ism eslab qolinadi: hostlar ro'yxati Zoom'dan
 * yangilanganda tizimdagi host nomi ("KarSU Zoom 1") o'qituvchi ismi bilan almashib ketmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zoom_hosts', function (Blueprint $table) {
            $table->string('renamed_to', 128)->nullable()->after('name');
            $table->timestamp('renamed_at')->nullable()->after('renamed_to');
        });
    }

    public function down(): void
    {
        Schema::table('zoom_hosts', fn (Blueprint $table) => $table->dropColumn(['renamed_to', 'renamed_at']));
    }
};
