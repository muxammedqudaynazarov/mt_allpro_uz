<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meet extends Model
{
    use HasFactory;

    protected $table = 'meets';

    protected $fillable = [
        'meet_id',
        'host_id',
        'topic',
        'user_id',
        'group_id',
        'time_id',
        'type',
        'start_url',
        'join_url',
        'started_at',
        'ended_at',
    ];

    public function group(): HasOne
    {
        return $this->hasOne(Group::class, 'id', 'group_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
