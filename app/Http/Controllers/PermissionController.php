<?php

namespace App\Http\Controllers;

use App\DTOs\PermissionDTO;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PermissionController extends Controller
{
    /**
     * Получить все разрешения
     */
    public function index(): JsonResponse
    {
        try {
            $permissions = Permission::with('roles')->get();
            $permissionsDTOs = $permissions->map(function ($permission) {
                $dto = PermissionDTO::fromModel($permission)->toArray();
                $dto['roles'] = $permission->roles;
                return $dto;
            });

            return response()->json([
                'success' => true,
                'permissions' => $permissionsDTOs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении разрешений',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Создать новое разрешение
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:191|unique:permissions,name',
                'description' => 'nullable|string',
                'code' => 'required|string|max:100|unique:permissions,code'
            ], [
                'name.required' => 'Наименование разрешения обязательно для заполнения',
                'name.unique' => 'Разрешение с таким наименованием уже существует',
                'code.required' => 'Код разрешения обязателен для заполнения',
                'code.unique' => 'Разрешение с таким кодом уже существует',
                'code.max' => 'Код разрешения не должен превышать 100 символов'
            ]);

            $permissionDTO = new PermissionDTO($validatedData);
            $permission = Permission::create($permissionDTO->toCreateArray());

            return response()->json([
                'success' => true,
                'message' => 'Разрешение успешно создано',
                'permission' => PermissionDTO::fromModel($permission)->toArray()
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании разрешения',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить конкретное разрешение
     */
    public function show(Permission $permission): JsonResponse
    {
        try {
            $permission->load('roles');
            
            return response()->json([
                'success' => true,
                'permission' => PermissionDTO::fromModel($permission)->toArray(),
                'roles' => $permission->roles,
                'roles_count' => $permission->roles->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении разрешения',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Обновить разрешение
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:191|unique:permissions,name,' . $permission->id,
                'description' => 'nullable|string',
                'code' => 'sometimes|string|max:100|unique:permissions,code,' . $permission->id
            ], [
                'name.unique' => 'Разрешение с таким наименованием уже существует',
                'code.unique' => 'Разрешение с таким кодом уже существует',
                'code.max' => 'Код разрешения не должен превышать 100 символов'
            ]);

            $permission->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Разрешение успешно обновлено',
                'permission' => PermissionDTO::fromModel($permission->fresh())->toArray()
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении разрешения',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Удалить разрешение
     */
    public function destroy(Permission $permission): JsonResponse
    {
        try {
            // Проверить, используется ли разрешение в ролях
            if ($permission->roles()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалить разрешение, которое используется в ролях. Сначала отвяжите его от всех ролей.'
                ], 422);
            }

            $permission->delete(); // Мягкое удаление

            return response()->json([
                'success' => true,
                'message' => 'Разрешение успешно удалено'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении разрешения',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
