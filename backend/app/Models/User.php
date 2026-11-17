<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Support\PersonName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property list<string> $roles
 * @property UserStatus $status
 * @property string|null $telegram_chat_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'first_name', 'last_name', 'middle_name',
        'hemis_id', 'picture_url', 'login', 'password',
        'roles', 'status', 'locale', 'can_manage_calendar',
        'telegram_chat_id', 'telegram_username', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'telegram_chat_id'];

    protected $attributes = [
        'roles' => '["teacher"]',
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'can_manage_calendar' => 'boolean',
        ];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        $own = $this->roles ?? [];
        foreach ($roles as $role) {
            if (in_array($role instanceof Role ? $role->value : $role, $own, true)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin);
    }

    public function isEducation(): bool
    {
        return $this->hasRole(Role::Education);
    }

    /** Barcha guruh va darslarni ko'radi: Administrator va O'quv bo'limi. */
    public function canViewAll(): bool
    {
        return $this->hasRole(Role::SuperAdmin, Role::Education);
    }

    /** Kalendar (har haftalik darslar) — o'qituvchiga administrator ruxsat bergan bo'lsa. */
    public function canManageCalendar(): bool
    {
        return $this->isSuperAdmin() || ($this->hasRole(Role::Teacher) && $this->can_manage_calendar !== false);
    }

    /** Telegram xabarlari va server matnlari uchun foydalanuvchi tili. */
    public function preferredLocale(): string
    {
        return in_array($this->locale, (array) config('app.supported_locales', ['kaa', 'uz']), true)
            ? $this->locale
            : (string) config('app.locale');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** @return list<string> */
    public function permissions(): array
    {
        $permissions = [];
        foreach ($this->roles ?? [] as $role) {
            $permissions = [...$permissions, ...(Role::tryFrom($role)?->permissions() ?? [])];
        }
        if ($this->canManageCalendar()) {
            $permissions[] = 'calendar.manage';
        }

        return array_values(array_unique($permissions));
    }

    /** "Qudaynazarov M." ko'rinishidagi qisqa ism. */
    public function shortName(): string
    {
        $last = $this->last_name ?: Str::before($this->name, ' ');
        $first = $this->first_name ?: Str::of($this->name)->after(' ')->before(' ')->toString();

        return trim($last.' '.($first !== '' && $first !== $last ? mb_substr($first, 0, 1).'.' : ''));
    }

    public function firstName(): string
    {
        return $this->first_name ?: (Str::of($this->name)->explode(' ')->get(1) ?? $this->name);
    }

    /**
     * Ko'rsatiladigan ism: "Familiya Ism" (otasining ismi ko'rsatilmaydi). Qismlar
     * bo'lmasa — saqlangan to'liq ismning birinchi ikki so'zi.
     */
    public function displayName(): string
    {
        if (filled($this->last_name) || filled($this->first_name)) {
            return PersonName::upper(trim(($this->last_name ?? '').' '.($this->first_name ?? '')));
        }

        return PersonName::upper(implode(' ', array_slice(preg_split('/\s+/u', trim((string) $this->name)) ?: [], 0, 2)));
    }

    /** Foydalanuvchilar ro'yxati: Administrator va O'quv bo'limi hammani ko'radi. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $viewer->canViewAll() ? $query : $query->whereKey($viewer->id);
    }

    /** "Familiya Ism Otasining ismi" — qismlardan to'liq ism. */
    public static function fullName(?string $last, ?string $first, ?string $middle): string
    {
        return trim(implode(' ', array_filter([$last, $first, $middle], fn (?string $part) => $part !== null && trim($part) !== '')));
    }

    public function scopeWithRole(Builder $query, Role|string $role): Builder
    {
        return $query->whereJsonContains('roles', $role instanceof Role ? $role->value : $role);
    }

    /**
     * HEMIS ism-familiyalarni ko'pincha katta harflarda qaytaradi:
     * "QUDAYNAZAROV MUXAMMED" → "Qudaynazarov Muxammed".
     */
    public static function normalizeName(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return null;
        }

        return mb_strtoupper($value) === $value ? mb_convert_case(mb_strtolower($value), MB_CASE_TITLE) : $value;
    }
}
