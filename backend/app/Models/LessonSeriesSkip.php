<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Takroriy darsning bekor qilingan bitta sanasi. */
class LessonSeriesSkip extends Model
{
    protected $fillable = ['lesson_series_id', 'date', 'created_by_id'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(LessonSeries::class, 'lesson_series_id');
    }
}
