<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Application extends Model
{
    use HasFactory;

    public static function group_name($group_id)
    {
        return DB::table('groups')
            ->where('hemis_id', $group_id)
            ->first()->name;
    }

    public static function user_name($hemis_id)
    {
        return strtolower(DB::table('users')->where('hemis_id', $hemis_id)
            ->first()->name);
    }

    public static function in_empty_group($group_id)
    {
        $time = DB::table('meets')
            ->where('_to', $group_id)
            ->orderBy('created_at', 'desc')
            ->first();
        //dd($time);
        if ($time)
            return (((strtotime($time->created_at) + 4800) < time()) || $time->status == 'o');
        else return true;
    }
}
