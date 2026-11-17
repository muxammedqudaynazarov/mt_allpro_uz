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

    /** Zoom email'i ochiq saqlanmaydi: tanish kaliti — uning SHA-256 xeshi. */
    public static function emailKey(string $email): string
    {
        return 'sha256:'.hash('sha256', mb_strtolower(trim($email)));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
