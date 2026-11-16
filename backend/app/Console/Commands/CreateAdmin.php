<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Bosh administratorni yaratadi yoki mavjudining zaxira parolini yangilaydi.
 * HEMIS ishlamay qolganda ham tizimga kirish imkonini beradi.
 *
 *   php artisan app:create-admin
 *   php artisan app:create-admin --login=admin --name="Ism Familiya" --hemis-id=1568
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--login= : Zaxira kirish uchun login}
        {--name= : Toʻliq ism}
        {--hemis-id= : HEMIS xodim ID (HEMIS orqali kirganda shu hisobga bogʻlanadi)}
        {--role=super_admin : super_admin yoki admin}';

    protected $description = 'Bosh administrator yaratish yoki uning zaxira parolini yangilash';

    public function handle(): int
    {
        $login = Str::lower($this->option('login') ?: text('Login', required: true, validate: fn ($v) => preg_match('/^[a-z0-9._-]{3,64}$/i', $v) ? null : 'Faqat lotin harflari, raqam, nuqta, chiziqcha (3–64 belgi)'));
        $role = Role::tryFrom((string) $this->option('role'));
        if (! $role || $role === Role::Teacher) {
            $this->error('--role faqat super_admin yoki admin boʻlishi mumkin.');

            return self::FAILURE;
        }

        $user = User::withTrashed()->where('login', $login)->first();
        $name = $this->option('name') ?: ($user?->name ?? text('Toʻliq ism', required: true));
        $plain = password('Parol (kamida 12 belgi)', required: true);

        $validator = Validator::make(['password' => $plain], ['password' => ['required', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));

            return self::FAILURE;
        }

        $user ??= new User;
        $user->fill([
            'name' => $name,
            'login' => $login,
            'password' => $plain,
            'status' => UserStatus::Active,
            'roles' => array_values(array_unique([...($user->roles ?? []), $role->value, Role::Teacher->value])),
        ]);
        if ($this->option('hemis-id')) {
            $user->hemis_id = (string) $this->option('hemis-id');
        }
        $user->deleted_at = null;
        $user->save();

        Audit::log('admin.cli', "Konsol orqali administrator yaratildi/yangilandi: {$user->name}", $user, actor: $user);
        $this->info("Tayyor: {$user->name} ({$login}) — {$role->label()}");

        return self::SUCCESS;
    }
}
