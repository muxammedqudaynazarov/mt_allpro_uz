<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index()
    {
        if (auth()->user()->can('groups:view')) {
            $groups = [];
            if (auth()->user()->current_role == 'super_admin')
                $groups = Group::orderBy('course')->get();
            elseif (auth()->user()->current_role == 'admin')
                $groups = Group::where('admin_id', auth()->id())->orderBy('course')->get();
            return view('groups.index', ['groups' => $groups]);
        }
    }

    public function create()
    {
    }

    public function store(Request $request)
    {
    }

    public function show(string $id)
    {
        if (auth()->user()->can('groups:show')) {
            $group = Group::find($id);
            if ($group) {
                return view('groups.show', ['group' => $group]);
            }
        }
    }

    public function edit(string $id)
    {
    }

    public function update(Request $request, string $id)
    {
    }

    public function destroy(string $id)
    {
    }
}
