<?php

namespace App\Http\Controllers;

use App\DTOs\RoleDTO;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $roles = Role::with('permissions')->get();
            $rolesDTOs = $roles->map(function ($role) {
                return RoleDTO::fromModel($role)->toArray();
            });

            return response()->json([
                'success' => true,
                'roles' => $rolesDTOs
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении ролей',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'description' => 'nullable|string',
                'code' => 'required|string|max:100|unique:roles,code'
            ]);

            $roleDTO = new RoleDTO($validatedData);
            $role = Role::create($roleDTO->toCreateArray());

            return response()->json([
                'success' => true,
                'message' => 'Роль успешно создана',
                'role' => RoleDTO::fromModel($role)->toArray()
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
                'message' => 'Ошибка при создании роли',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Role $role): JsonResponse
    {
        try {
            $role->load('permissions', 'users');
            
            return response()->json([
                'success' => true,
                'role' => RoleDTO::fromModel($role)->toArray(),
                'permissions' => $role->permissions,
                'users_count' => $role->users->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении роли',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255|unique:roles,name,' . $role->id,
                'description' => 'nullable|string',
                'code' => 'sometimes|string|max:100|unique:roles,code,' . $role->id
            ]);

            $role->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Роль успешно обновлена',
                'role' => RoleDTO::fromModel($role->fresh())->toArray()
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
                'message' => 'Ошибка при обновлении роли',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Role $role): JsonResponse
    {
        try {
            $role->delete(); // Мягкое удаление

            return response()->json([
                'success' => true,
                'message' => 'Роль успешно удалена'
            ], 200);


        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении роли',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
