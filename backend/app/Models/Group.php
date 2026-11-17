<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'hemis_id', 'course', 'zoom_account_id', 'zoom_user_email', 'is_active'];

    protected function casts(): array
    {
        return [
            'course' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Zoom email'lari katta-kichik harfga sezgir emas — bir xil ko'rinishda saqlaymiz. */
    protected function zoomUserEmail(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    protected static function booted(): void
    {
        static::saved(function (Group $group) {
            if ($group->zoom_account_id && $group->zoom_user_email) {
                ZoomHost::firstOrCreate(
                    ['zoom_account_id' => $group->zoom_account_id, 'email' => mb_strtolower($group->zoom_user_email)],
                    ['name' => mb_strtolower($group->zoom_user_email), 'type' => ZoomHost::LICENSED, 'is_active' => true],
                );
            }
        });
    }

    public function zoomAccount(): BelongsTo
    {
        return $this->belongsTo(ZoomAccount::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /** Guruhlar ro'yxati: Administrator va O'quv bo'limi hammasini ko'radi. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $viewer->canViewAll() ? $query : $query->whereRaw('1 = 0');
    }
}
