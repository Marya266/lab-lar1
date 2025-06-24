<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Создать разрешения
        $permissions = [
            ['name' => 'Создание пользователей', 'code' => 'users.create', 'description' => 'Разрешение на создание новых пользователей'],
            ['name' => 'Просмотр пользователей', 'code' => 'users.view', 'description' => 'Разрешение на просмотр списка пользователей'],
            ['name' => 'Редактирование пользователей', 'code' => 'users.edit', 'description' => 'Разрешение на редактирование пользователей'],
            ['name' => 'Удаление пользователей', 'code' => 'users.delete', 'description' => 'Разрешение на удаление пользователей'],
            ['name' => 'Управление ролями', 'code' => 'roles.manage', 'description' => 'Разрешение на управление ролями'],
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Создать роли
        $adminRole = Role::create([
            'name' => 'Администратор',
            'code' => 'admin',
            'description' => 'Полный доступ к системе'
        ]);

        $moderatorRole = Role::create([
            'name' => 'Модератор',
            'code' => 'moderator', 
            'description' => 'Ограниченный доступ к управлению пользователями'
        ]);

        $userRole = Role::create([
            'name' => 'Пользователь',
            'code' => 'user',
            'description' => 'Базовые права пользователя'
        ]);

        // Назначить разрешения ролям
        $adminRole->permissions()->attach(Permission::all());
        $moderatorRole->permissions()->attach(Permission::whereIn('code', ['users.view', 'users.edit'])->get());
        $userRole->permissions()->attach(Permission::where('code', 'users.view')->get());
    }
}
