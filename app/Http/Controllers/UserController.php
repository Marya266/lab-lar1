<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\DTOs\UserResourceDTO;
use App\Models\User;
use Illuminate\Http\JsonResponse;


class UserController extends Controller
{
     public function index(): JsonResponse
    {
        try {
            $users = User::all();
            $userResources = $users->map(function ($user) {
                return new UserResourceDTO($user->toArray());
            });
            
            return response()->json([
                'success' => true,
                'users' => $userResources->map->toArray()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении пользователей',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function show(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userResource = new UserResourceDTO($user->toArray());
            
            return response()->json([
                'success' => true,
                'user' => $userResource->toArray()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении данных пользователя',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function update(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            ]);
            
            $user->update($validatedData);
            $userResource = new UserResourceDTO($user->fresh()->toArray());
            
            return response()->json([
                'success' => true,
                'message' => 'Данные пользователя обновлены',
                'user' => $userResource->toArray()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении данных пользователя',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
