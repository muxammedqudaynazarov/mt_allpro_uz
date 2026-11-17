<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Guruh talabasi. Talabalar tizimga kirmaydi — ular Zoom'dagi ismi (va Zoom'ga
 * kirgan bo'lsa, Zoom ID/email) bo'yicha avtomatik aniqlanadi. Administrator
 * bir talabaning turli yozuvlarini birlashtirishi mumkin, tizim buni eslab qoladi.
 */
class Student extends Model
{
    use HasFactory;

    protected $fillable = ['group_id', 'name', 'is_ignored'];

    protected function casts(): array
    {
        return ['is_ignored' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class)->withTrashed();
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(StudentAlias::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(ZoomParticipation::class);
    }

    public function scopeCounted(Builder $query): Builder
    {
        return $query->where('is_ignored', false);
    }
}
