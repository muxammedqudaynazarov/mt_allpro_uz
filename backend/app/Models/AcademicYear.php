<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** O'quv yili ("2026–2027"): Kuzgi va Bahorgi semestrlar. */
class AcademicYear extends Model
{
    use HasFactory;

    public const PLANNED = 'planned';

    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    public const STATUSES = [self::PLANNED, self::ACTIVE, self::ARCHIVED];

    protected $fillable = ['name', 'status'];

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class)->orderBy('starts_on');
    }
}
