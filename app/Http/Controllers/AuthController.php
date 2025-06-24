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
use App\Services\TwoFactorService;
use Illuminate\Validation\ValidationException;
use DB;

class AuthController extends Controller
{
    private TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

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
                
                // Проверить включена ли 2FA
                if ($user->hasTwoFactorEnabled()) {
                    // Сохранить ID пользователя для второго этапа аутентификации
                    $sessionId = 'pending_2fa_' . uniqid();
                    cache()->put($sessionId, $user->id, now()->addMinutes(5));
                    
                    return response()->json([
                        'success' => true,
                        'requires_2fa' => true,
                        'message' => 'Требуется код двухфакторной аутентификации',
                        'session_id' => $sessionId
                    ], 200);
                }
                
                // Удалить все предыдущие токены пользователя
                $user->tokens()->delete();
                
                // Создать новый токен
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

    /**
     * Подтверждение 2FA кода при входе
     */
    public function verify2FA(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'session_id' => 'required|string',
                'code' => 'required|string',
            ], [
                'session_id.required' => 'ID сессии обязателен',
                'code.required' => 'Код 2FA обязателен'
            ]);

            // Получить ID пользователя из кэша
            $userId = cache()->get($validatedData['session_id']);
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сессия истекла. Авторизуйтесь заново.'
                ], 422);
            }

            $user = User::find($userId);
            
            if (!$user || !$user->hasTwoFactorEnabled()) {
                cache()->forget($validatedData['session_id']);
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка аутентификации'
                ], 422);
            }

            // Проверить 2FA код
            if (!$this->twoFactorService->verifyLoginCode($user, $validatedData['code'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный код 2FA'
                ], 422);
            }

            // Удалить сессию 2FA
            cache()->forget($validatedData['session_id']);

            DB::beginTransaction();

            // Удалить все предыдущие токены пользователя
            $user->tokens()->delete();
            
            // Создать новый токен
            $token = $user->createToken('auth_token')->plainTextToken;
            
            $userResource = new UserResourceDTO($user->toArray());

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Успешная авторизация с 2FA',
                'user' => $userResource->toArray(),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при подтверждении 2FA',
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
