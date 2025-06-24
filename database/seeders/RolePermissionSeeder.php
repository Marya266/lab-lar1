<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Создать разрешения с проверкой существования
        $permissions = [
            // Пользователи
            ['name' => 'Просмотр пользователей', 'code' => 'users.view', 'description' => 'Разрешение на просмотр списка пользователей'],
            ['name' => 'Создание пользователей', 'code' => 'users.create', 'description' => 'Разрешение на создание новых пользователей'],
            ['name' => 'Редактирование пользователей', 'code' => 'users.edit', 'description' => 'Разрешение на редактирование пользователей'],
            ['name' => 'Удаление пользователей', 'code' => 'users.delete', 'description' => 'Разрешение на удаление пользователей'],
            
            // Роли и разрешения
            ['name' => 'Управление ролями', 'code' => 'roles.manage', 'description' => 'Разрешение на управление ролями и разрешениями'],
            
            // Логи
            ['name' => 'Просмотр логов', 'code' => 'logs.view', 'description' => 'Разрешение на просмотр логов изменений'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['code' => $permission['code']], // Проверяем по коду
                $permission // Создаем или обновляем данными
            );
        }

        // Создать роли с проверкой существования
        $adminRole = Role::updateOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Администратор',
                'code' => 'admin',
                'description' => 'Полный доступ к системе'
            ]
        );

        $moderatorRole = Role::updateOrCreate(
            ['code' => 'moderator'],
            [
                'name' => 'Модератор',
                'code' => 'moderator',
                'description' => 'Ограниченный доступ к управлению пользователями'
            ]
        );

        $userRole = Role::updateOrCreate(
            ['code' => 'user'],
            [
                'name' => 'Пользователь',
                'code' => 'user',
                'description' => 'Базовые права пользователя'
            ]
        );

        // Назначить разрешения ролям (проверка на существование связи)
        if ($adminRole->permissions()->count() == 0) {
            $adminRole->permissions()->attach(Permission::all()); // Все разрешения
        }

        if ($moderatorRole->permissions()->count() == 0) {
            $moderatorRole->permissions()->attach(Permission::whereIn('code', [
                'users.view', 
                'users.edit', 
                'logs.view'
            ])->get());
        }

        if ($userRole->permissions()->count() == 0) {
            $userRole->permissions()->attach(Permission::where('code', 'users.view')->get());
        }

        // Создать тестовых пользователей с проверкой существования
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Администратор',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123')
            ]
        );
        
        if (!$admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        $moderator = User::updateOrCreate(
            ['email' => 'moderator@example.com'],
            [
                'name' => 'Модератор',
                'email' => 'moderator@example.com',
                'password' => Hash::make('password123')
            ]
        );
        
        if (!$moderator->hasRole('moderator')) {
            $moderator->assignRole($moderatorRole);
        }


        $user = User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Пользователь',
                'email' => 'user@example.com',
                'password' => Hash::make('password123')
            ]
        );
        
        if (!$user->hasRole('user')) {
            $user->assignRole($userRole);
        }

        $this->command->info('Сидер выполнен успешно!');
        $this->command->info('Тестовые аккаунты:');
        $this->command->info('Админ: admin@example.com / password123');
        $this->command->info('Модератор: moderator@example.com / password123');
        $this->command->info('Пользователь: user@example.com / password123');
    }
}
