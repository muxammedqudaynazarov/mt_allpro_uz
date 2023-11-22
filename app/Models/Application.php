<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Application extends Model
{
    use HasFactory;

    public static function group_name($group_id) {
        return DB::table('groups')
            ->where('hemis_id', $group_id)
            ->first()->name;
    }
}
