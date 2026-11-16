<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'hemis_id', 'hemis_uuid', 'hemis_login', 'department', 'picture_url',
        'email', 'phone', 'login', 'password',
        'roles', 'status', 'managed_by_id',
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
        ];
    }

    public function managedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by_id')->withTrashed();
    }

    public function managedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'managed_by_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function adminGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'admin_id');
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

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin, Role::SuperAdmin);
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

    /** Administrator ko'ra oladigan foydalanuvchilar. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('managed_by_id', $viewer->id)
            ->orWhere('status', UserStatus::Pending->value));
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
