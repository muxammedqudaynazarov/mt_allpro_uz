<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shaxsga doir ma'lumotlarni kamaytirish: xodimlardan faqat F.I.Sh., HEMIS ID va rasm
 * saqlanadi. Telefon, email, bo'lim, HEMIS login/UUID — o'chiriladi.
 * Talabalar: Zoom'dagi email ochiq holda saqlanmaydi — tanish uchun faqat uning
 * bir tomonlama xeshi (SHA-256) qoladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['hemis_uuid']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['hemis_uuid', 'hemis_login', 'department', 'email', 'phone']);
        });

        DB::table('zoom_participations')->update(['email' => null]);
        DB::table('live_participants')->update(['email' => null]);

        DB::table('student_aliases')->where('type', 'email')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                if (! str_starts_with((string) $row->value, 'sha256:')) {
                    $hash = 'sha256:'.hash('sha256', mb_strtolower(trim((string) $row->value)));
                    // Bir xil xesh allaqachon bo'lsa (takroriy yozuv) — eskisi o'chiriladi.
                    $exists = DB::table('student_aliases')->where(['group_id' => $row->group_id, 'type' => 'email', 'value' => $hash])->exists();
                    $exists
                        ? DB::table('student_aliases')->where('id', $row->id)->delete()
                        : DB::table('student_aliases')->where('id', $row->id)->update(['value' => $hash]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('hemis_uuid', 64)->nullable()->unique();
            $table->string('hemis_login', 64)->nullable();
            $table->string('department')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
        });
    }
};
