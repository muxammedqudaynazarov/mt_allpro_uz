<?php

namespace App\Actions;

use App\Models\ZoomAccount;
use App\Models\ZoomHost;
use App\Services\Zoom\ZoomClient;

/**
 * Zoom akkauntidagi foydalanuvchilarni hostlar ro'yxatiga yozadi. Yangi Licensed
 * foydalanuvchi darhol faol bo'ladi; Basic (40 daqiqa cheklovi) — nofaol, administrator
 * xohlasa yoqadi. Zoom'dan o'chirilganlar nofaol qilinadi.
 */
class SyncZoomHosts
{
    public function __construct(private readonly ZoomClient $zoom) {}

    /** @return int Zoom'dagi faol foydalanuvchilar soni */
    public function handle(ZoomAccount $account): int
    {
        $users = $this->zoom->users($account);
        $seen = [];

        foreach ($users as $user) {
            $email = mb_strtolower(trim($user['email']));
            if ($email === '') {
                continue;
            }
            $seen[] = $email;
            $host = ZoomHost::firstOrNew(['zoom_account_id' => $account->id, 'email' => $email]);
            $isNew = ! $host->exists;
            // Zoom'dagi ism darsni boshlashda o'qituvchi nomiga o'zgartirilgan bo'lsa — tizimdagi nom saqlanadi.
            $renamed = $host->exists && $host->renamed_to !== null && $user['name'] === $host->renamed_to;
            $host->fill([
                'zoom_user_id' => $user['id'] ?: $host->zoom_user_id,
                'name' => $renamed ? $host->name : ($user['name'] !== '' ? $user['name'] : $email),
                'type' => $user['type'] === ZoomHost::BASIC ? ZoomHost::BASIC : ZoomHost::LICENSED,
            ]);
            if ($isNew) {
                $host->is_active = $host->type === ZoomHost::LICENSED;
            }
            $host->save();
        }

        ZoomHost::query()->where('zoom_account_id', $account->id)->whereNotIn('email', $seen)->update(['is_active' => false]);

        return count($users);
    }
}
