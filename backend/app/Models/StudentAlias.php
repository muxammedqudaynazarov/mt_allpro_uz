<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Talabani tanish kaliti: normallashtirilgan ism, Zoom foydalanuvchi ID yoki email. */
class StudentAlias extends Model
{
    public const NAME = 'name';

    public const ZOOM_ID = 'zoom_id';

    public const EMAIL = 'email';

    protected $fillable = ['student_id', 'group_id', 'type', 'value'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
