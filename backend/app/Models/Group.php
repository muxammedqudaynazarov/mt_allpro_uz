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

    protected $fillable = ['name', 'hemis_id', 'course', 'zoom_account_id', 'zoom_user_email', 'admin_id', 'is_active'];

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

    public function zoomAccount(): BelongsTo
    {
        return $this->belongsTo(ZoomAccount::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id')->withTrashed();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /** Guruhlar ro'yxati: bosh administrator hammasini, administrator o'zinikini ko'radi. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $viewer->isSuperAdmin() ? $query : $query->where('admin_id', $viewer->id);
    }

    /** Dars yaratish mumkin bo'lgan guruhlar. */
    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('zoomAccount', fn (Builder $q) => $q->where('is_active', true));
    }
}
