<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollar qayta tuzildi: "admin" (o'ziga biriktirilgan guruhlarni boshqaruvchi)
 * o'rniga "education" — O'quv bo'limi (barcha guruhlarni kuzatadi, guruh
 * biriktirilmaydi). O'qituvchiga kalendarni boshqarish ruxsati (standart: bor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_manage_calendar')->default(true)->after('status');
        });

        $this->replaceRole('admin', 'education');
    }

    public function down(): void
    {
        $this->replaceRole('education', 'admin');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_manage_calendar');
        });
    }

    private function replaceRole(string $from, string $to): void
    {
        DB::table('users')->select(['id', 'roles'])->orderBy('id')->get()->each(function (object $user) use ($from, $to) {
            $roles = json_decode((string) $user->roles, true) ?: [];
            if (in_array($from, $roles, true)) {
                $roles = array_values(array_unique(array_map(fn (string $role) => $role === $from ? $to : $role, $roles)));
                DB::table('users')->where('id', $user->id)->update(['roles' => json_encode($roles)]);
            }
        });
    }
};
