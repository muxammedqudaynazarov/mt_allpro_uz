<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Group extends Model
{
    use HasFactory;

    protected $table = 'groups';

    protected $fillable = [
        'hemis_id',
        'admin_id',
        'parent_id',
        'name',
        'email',
        'api',
        'active',
        'course',
        'account_id',
        'client_id',
        'client_secret',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Group::class, 'parent_id', 'id');
    }
}
