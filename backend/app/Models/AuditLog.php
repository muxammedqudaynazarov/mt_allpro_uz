<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'description', 'subject_type', 'subject_id', 'properties', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['properties' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Ikki yildan eski yozuvlar avtomatik tozalanadi (model:prune). */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subYears(2));
    }
}
