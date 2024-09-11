<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'lessons:view',
            'lessons:create',
            'lessons:delete',

            'teachers:view',
            'teachers:create',
            'teachers:edit',
            'teachers:delete',
            'teachers:status',
            'teachers:password',

            'groups:view',
            'groups:show',
            'groups:create',
            'groups:edit',
            'groups:status',
        ];
        foreach ($permissions as $name) {
            Permission::create(['name' => $name]);
        }

        $pos = [
            'super_admin' => 'Супервайзер',
            'admin' => 'Администратор',
            'moder' => 'Модератор',
            'teacher' => 'Ўқитувчи'
        ];
        $roles = [
            'admin' => [
                'lessons:view',
                'lessons:create',
                'lessons:delete',

                'teachers:view',
                'teachers:create',
                'teachers:edit',
                'teachers:delete',
                'teachers:status',
                'teachers:password',

                'groups:view',
                'groups:show',
                'groups:status',
            ],
            'teacher' => [
                'lessons:view',
                'lessons:create',
                'lessons:delete',
            ],
        ];

        foreach ($roles as $name => $value) {
            $role = Role::create([
                'name' => $name,
                'desc' => $pos[$name],
            ]);
            $role->syncPermissions($value);
        }
    }
}
