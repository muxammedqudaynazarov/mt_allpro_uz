<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dars o'tkaziladigan Zoom foydalanuvchisi (litsenziya). Bir host bir vaqtda faqat
 * bitta uchrashuv o'tkaza oladi, shuning uchun o'qituvchi dars yaratishda bo'sh hostni tanlaydi.
 */
class ZoomHost extends Model
{
    use HasFactory;

    public const BASIC = 1;

    public const LICENSED = 2;

    protected $fillable = ['zoom_account_id', 'zoom_user_id', 'email', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['type' => 'integer', 'is_active' => 'boolean'];
    }

    public function zoomAccount(): BelongsTo
    {
        return $this->belongsTo(ZoomAccount::class);
    }

    /** Dars uchun tanlash mumkin bo'lgan hostlar (host va uning akkaunti faol). */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('zoomAccount', fn (Builder $q) => $q->where('is_active', true));
    }

    public function isLicensed(): bool
    {
        return $this->type !== self::BASIC;
    }

    /** Guruhning standart hosti (guruhda akkaunt va email ko'rsatilgan bo'lsa). */
    public static function forGroup(Group $group): ?self
    {
        if (! $group->zoom_account_id || ! $group->zoom_user_email) {
            return null;
        }

        return self::query()->where('zoom_account_id', $group->zoom_account_id)->where('email', mb_strtolower($group->zoom_user_email))->first();
    }
}
