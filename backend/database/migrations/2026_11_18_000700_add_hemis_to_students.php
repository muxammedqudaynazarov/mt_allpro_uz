<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talabaning HEMIS ID raqami (O'quv bo'limi kiritadi) va HEMIS'dan olingan haqiqiy
 * F.I.Sh. hamda rasm. hemis_synced_id — oxirgi so'ralgan ID: o'zgarmagan ID qayta so'ralmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('hemis_id', 32)->nullable()->after('name');
            $table->string('picture_url')->nullable()->after('hemis_id');
            $table->string('hemis_synced_id', 32)->nullable()->after('picture_url');
            $table->timestamp('hemis_synced_at')->nullable()->after('hemis_synced_id');
            $table->string('hemis_error', 191)->nullable()->after('hemis_synced_at');

            $table->unique(['group_id', 'hemis_id']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'hemis_id']);
            $table->dropColumn(['hemis_id', 'picture_url', 'hemis_synced_id', 'hemis_synced_at', 'hemis_error']);
        });
    }
};
