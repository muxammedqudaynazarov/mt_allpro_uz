<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasPermissions, HasRoles;

    protected $fillable = [
        'name',
        'username',
        'password',
        'hemis_id',
        'phone',
        'telegram_id',
        'admin_id',
        'current_role',
        'roles_list',
        'status'
    ];

    public function admin(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'admin_id');
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
        'password' => 'hashed',
    ];
}
