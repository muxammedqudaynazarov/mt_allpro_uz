<?php

namespace App\Console\Commands;

use App\Enums\LessonStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSlot;
use App\Models\User;
use App\Models\ZoomAccount;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Eski (Laravel 10) tizim bazasidan ma'lumotlarni ko'chiradi:
 * Zoom akkauntlar, guruhlar, dars vaqtlari, o'qituvchilar va darslar tarixi.
 *
 * Eski baza .env'dagi LEGACY_DB_* sozlamalari orqali ulanadi.
 * Parollar va Zoom tokenlari ko'chirilmaydi: o'qituvchilar endi HEMIS orqali kiradi,
 * Zoom tokenlari esa avtomatik olinadi.
 */
class ImportLegacy extends Command
{
    protected $signature = 'legacy:import {--connection=legacy : Eski baza ulanishi nomi} {--force : Yangi bazada maʼlumot boʻlsa ham davom etish}';

    protected $description = 'Eski mt.allpro.uz bazasidan maʼlumotlarni koʻchirish';

    /** @var array<int, int> */
    private array $users = [];

    /** @var array<int, int> */
    private array $groups = [];

    /** @var array<int, int> */
    private array $slots = [];

    public function handle(): int
    {
        $legacy = DB::connection($this->option('connection'));

        try {
            $legacy->getPdo();
        } catch (Throwable $e) {
            $this->error('Eski bazaga ulanib boʻlmadi: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $this->option('force') && (Group::withTrashed()->exists() || Lesson::exists())) {
            $this->error('Yangi bazada allaqachon guruh yoki darslar bor. Takroriy import oldini olish uchun toʻxtatildi (--force bilan majburlash mumkin).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($legacy) {
            $this->importSlots($legacy);
            $this->importUsers($legacy);
            $this->importGroups($legacy);
            $this->importLessons($legacy);
        });

        $this->newLine();
        $this->info('Import yakunlandi.');
        $this->line('Keyingi qadam: `php artisan app:create-admin` bilan bosh administrator yarating va Zoom akkauntlar sahifasida “Ulanishni tekshirish”ni bosing.');

        return self::SUCCESS;
    }

    private function importSlots(ConnectionInterface $legacy): void
    {
        if (! $this->hasTable($legacy, 'times')) {
            return;
        }
        $rows = $legacy->table('times')->orderBy('code')->get();
        foreach ($rows->values() as $index => $row) {
            $start = preg_match('/^\d{1,2}:\d{2}$/', (string) $row->name) ? str_pad((string) $row->name, 5, '0', STR_PAD_LEFT) : null;
            if (! $start) {
                continue;
            }
            $slot = LessonSlot::firstOrCreate(['starts_at' => $start], [
                'name' => LessonSlot::defaultName($index + 1),
                'duration_minutes' => 80,
                'sort' => $index + 1,
                'is_active' => (string) ($row->status ?? '1') === '1',
            ]);
            $this->slots[(int) $row->id] = $slot->id;
        }
        $this->line('Dars vaqtlari: '.count($this->slots));
    }

    private function importUsers(ConnectionInterface $legacy): void
    {
        $rows = $legacy->table('users')->orderBy('id')->get();
        $skipped = 0;

        foreach ($rows as $row) {
            $roles = $this->roles($row);
            $hemisId = isset($row->hemis_id) && $row->hemis_id ? (string) $row->hemis_id : null;
            $chatId = isset($row->telegram_id) && $row->telegram_id ? (string) $row->telegram_id : null;

            if ($hemisId && User::withTrashed()->where('hemis_id', $hemisId)->exists()) {
                $this->users[(int) $row->id] = User::withTrashed()->where('hemis_id', $hemisId)->value('id');
                $skipped++;

                continue;
            }
            if ($chatId && User::where('telegram_chat_id', $chatId)->exists()) {
                $chatId = null;
            }

            $user = User::create([
                'name' => User::normalizeName((string) $row->name) ?? 'Nomaʼlum',
                'hemis_id' => $hemisId,
                'telegram_chat_id' => $chatId,
                'roles' => $roles,
                'status' => ($row->status ?? 'active') === 'active' ? UserStatus::Active : UserStatus::Blocked,
            ]);
            $user->forceFill(['created_at' => $row->created_at ?? now()])->saveQuietly();
            $this->users[(int) $row->id] = $user->id;
        }

        $this->line('Foydalanuvchilar: '.count($this->users).($skipped ? " (shundan {$skipped} tasi avval mavjud edi)" : ''));
        $withoutHemis = $rows->filter(fn ($row) => empty($row->hemis_id))->count();
        if ($withoutHemis) {
            $this->warn("{$withoutHemis} ta xodimda HEMIS ID yoʻq — ular HEMIS orqali kirganda yangi hisob ochiladi. “Foydalanuvchilar” sahifasida HEMIS ID ni kiriting.");
        }
    }

    private function importGroups(ConnectionInterface $legacy): void
    {
        $rows = $legacy->table('groups')->orderBy('id')->get();
        $byId = $rows->keyBy('id');
        $accounts = [];

        foreach ($rows as $row) {
            // Eski tizimda bola guruhlar Zoom kalitlarini ota guruhdan olgan.
            $source = $row;
            if (empty($row->client_id) && ! empty($row->parent_id) && $byId->has($row->parent_id)) {
                $source = $byId->get($row->parent_id);
            }
            if (empty($source->account_id) || empty($source->client_id) || empty($source->client_secret)) {
                $this->warn("Guruh oʻtkazib yuborildi (Zoom kalitlari yoʻq): {$row->name}");

                continue;
            }

            $key = $source->account_id.'|'.$source->client_id;
            $accounts[$key] ??= ZoomAccount::firstOrCreate(
                ['account_id' => $source->account_id, 'client_id' => $source->client_id],
                ['name' => 'Zoom akkaunt '.(count($accounts) + 1), 'client_secret' => $source->client_secret, 'is_active' => true],
            )->id;

            $hemisId = ! empty($row->hemis_id) ? (string) $row->hemis_id : null;
            if ($hemisId && Group::withTrashed()->where('hemis_id', $hemisId)->exists()) {
                $hemisId = null;
            }

            $group = Group::create([
                'name' => trim((string) $row->name),
                'hemis_id' => $hemisId,
                'course' => max(1, (int) ($row->course ?? 1)),
                'zoom_account_id' => $accounts[$key],
                'zoom_user_email' => mb_strtolower(trim((string) $row->email)),
                'is_active' => (string) ($row->active ?? '1') === '1',
            ]);
            $this->groups[(int) $row->id] = $group->id;
        }

        $this->line('Zoom akkauntlar: '.count($accounts).', guruhlar: '.count($this->groups));
        if (count($accounts)) {
            $this->warn('Eski kodda Zoom kalitlari ochiq saqlangan edi. Zoom Marketplace’da Client Secret’larni yangilab, tizimga yangi qiymatni kiriting.');
        }
    }

    private function importLessons(ConnectionInterface $legacy): void
    {
        if (! $this->hasTable($legacy, 'meets') || ! Schema::connection($legacy->getName())->hasColumn('meets', 'group_id')) {
            $this->warn('Darslar tarixi topilmadi yoki eski formatda — oʻtkazib yuborildi.');

            return;
        }

        $count = 0;
        $legacy->table('meets')->orderBy('id')->chunk(500, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $userId = $this->users[(int) $row->user_id] ?? null;
                $groupId = $this->groups[(int) $row->group_id] ?? null;
                if (! $userId || ! $groupId) {
                    continue;
                }
                $group = Group::find($groupId);
                $start = Carbon::parse($row->started_at ?? $row->created_at);
                $end = $row->ended_at ? Carbon::parse($row->ended_at) : $start->copy()->addMinutes(80);

                Lesson::create([
                    'user_id' => $userId,
                    'group_id' => $groupId,
                    'lesson_slot_id' => $this->slots[(int) ($row->time_id ?? 0)] ?? null,
                    'zoom_account_id' => $group->zoom_account_id,
                    'zoom_meeting_id' => (string) $row->meet_id,
                    'zoom_host_email' => $group->zoom_user_email,
                    'topic' => mb_substr((string) ($row->topic ?: $group->name), 0, 255),
                    'join_url' => null,
                    'start_url' => null,
                    'status' => LessonStatus::Finished,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'ended_at' => $end,
                ]);
                $count++;
            }
        });

        $this->line("Darslar tarixi: {$count}");
    }

    /** @return list<string> */
    private function roles(object $row): array
    {
        $list = json_decode((string) ($row->roles_list ?? '[]'), true) ?: [];
        $list[] = $row->current_role ?? 'teacher';
        // Eski tizimdagi "admin" (guruh mas'uli) yangi tizimda O'quv bo'limi.
        $list = array_map(fn (string $role) => $role === 'admin' ? Role::Education->value : $role, $list);
        $roles = array_values(array_intersect(array_unique($list), Role::values()));

        return $roles ?: [Role::Teacher->value];
    }

    private function hasTable(ConnectionInterface $legacy, string $table): bool
    {
        return Schema::connection($legacy->getName())->hasTable($table);
    }
}
