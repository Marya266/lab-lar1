<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UserRegistrationRequest;
use App\Http\Requests\UserAuthRequest;
use App\DTOs\UserResourceDTO;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;


class AuthController extends Controller
{
    public function register(UserRegistrationRequest $request): JsonResponse
    {
        try {
            $dto = $request->toDTO();
            
            $user = User::create([
                'name' => $dto->getName(),
                'email' => $dto->getEmail(),
                'password' => Hash::make($dto->getPassword()),
            ]);
            
            $userResource = new UserResourceDTO($user->toArray());
            
            return response()->json([
                'success' => true,
                'message' => 'Пользователь успешно зарегистрирован',
                'user' => $userResource->toArray()
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при регистрации пользователя',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function login(UserAuthRequest $request): JsonResponse
    {
        try {
            $dto = $request->toDTO();
            
            if (Auth::attempt(['email' => $dto->getEmail(), 'password' => $dto->getPassword()])) {
                $user = Auth::user();
                $token = $user->createToken('auth_token')->plainTextToken;
                
                $userResource = new UserResourceDTO($user->toArray());
                
                return response()->json([
                    'success' => true,
                    'message' => 'Успешная авторизация',
                    'user' => $userResource->toArray(),
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ], 200);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Неверные учетные данные'
            ], 401);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при авторизации',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Успешный выход из системы'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при выходе из системы',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
