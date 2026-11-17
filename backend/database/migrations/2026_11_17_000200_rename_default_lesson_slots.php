<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Standart dars vaqti nomlari "1-para" edi. Endi bazada asosiy til shaklida
 * "1-juplıq" saqlanadi (ekranda o'zbekchada "1-juftlik" bo'lib ko'rinadi).
 * Administrator o'zi bergan boshqa nomlarga tegilmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('/^\s*(\d+)\s*-\s*(para|juftlik)\s*$/iu', fn (string $number) => $number.'-juplıq');
    }

    public function down(): void
    {
        $this->rename('/^\s*(\d+)\s*-\s*juplıq\s*$/iu', fn (string $number) => $number.'-para');
    }

    private function rename(string $pattern, Closure $name): void
    {
        DB::table('lesson_slots')->select(['id', 'name'])->orderBy('id')->get()->each(function (object $slot) use ($pattern, $name) {
            if (preg_match($pattern, (string) $slot->name, $match)) {
                DB::table('lesson_slots')->where('id', $slot->id)->update(['name' => $name($match[1])]);
            }
        });
    }
};
