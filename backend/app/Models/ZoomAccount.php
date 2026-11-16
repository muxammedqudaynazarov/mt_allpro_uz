<?php

namespace App\Models;

use Database\Factories\ZoomAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZoomAccount extends Model
{
    /** @use HasFactory<ZoomAccountFactory> */
    use HasFactory;

    protected $fillable = ['name', 'account_id', 'client_id', 'client_secret', 'is_active', 'last_checked_at', 'last_error'];

    protected $hidden = ['client_secret'];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }
}
