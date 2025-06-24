<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use App\DTOs\TwoFactorSetupDTO;
use App\DTOs\TwoFactorStatusDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    private TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Получить статус 2FA пользователя
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $statusData = [
                'enabled' => $user->hasTwoFactorEnabled(),
                'recovery_codes_count' => $user->two_factor_recovery_codes ? count($user->two_factor_recovery_codes) : 0,
                'enabled_at' => $user->two_factor_confirmed_at?->toISOString()
            ];

            $dto = new TwoFactorStatusDTO($statusData);

            return response()->json([
                'success' => true,
                'two_factor' => $dto->toArray()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статуса 2FA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Начать процесс включения 2FA
     */
    public function setup(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA уже включена'
                ], 422);
            }

            $secret = $this->twoFactorService->generateSecret();
            $qrCodeUrl = $this->twoFactorService->getQrCodeUrl($user, $secret);

            $setupData = [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'manual_entry_key' => $secret
            ];

            $dto = new TwoFactorSetupDTO($setupData);

            // Сохранить временный секрет в сессии или кэше
            cache()->put("2fa_setup_{$user->id}", $secret, now()->addMinutes(10));

            return response()->json([
                'success' => true,
                'message' => 'Настройка 2FA начата',
                'setup' => $dto->toArray()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при настройке 2FA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Подтвердить и включить 2FA
     */
    public function enable(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'code' => 'required|string|size:6',
            ], [
                'code.required' => 'Код подтверждения обязателен',
                'code.size' => 'Код должен содержать 6 цифр'
            ]);

            $user = $request->user();

            if ($user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA уже включена'
                ], 422);
            }

            // Получить временный секрет
            $secret = cache()->get("2fa_setup_{$user->id}");
            
            if (!$secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сессия настройки истекла. Начните процесс заново.'
                ], 422);
            }

            DB::beginTransaction();


            if (!$this->twoFactorService->enableTwoFactor($user, $secret, $validatedData['code'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный код подтверждения'
                ], 422);
            }

            // Удалить временный секрет
            cache()->forget("2fa_setup_{$user->id}");

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '2FA успешно включена',
                'recovery_codes' => $user->fresh()->two_factor_recovery_codes
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
                'message' => 'Ошибка при включении 2FA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Отключить 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'code' => 'required|string',
                'password' => 'required|string'
            ], [
                'code.required' => 'Код подтверждения обязателен',
                'password.required' => 'Пароль обязателен для отключения 2FA'
            ]);

            $user = $request->user();

            if (!$user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA не включена'
                ], 422);
            }

            // Проверить пароль
            if (!password_verify($validatedData['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный пароль'
                ], 422);
            }

            // Проверить 2FA код
            if (!$this->twoFactorService->verifyLoginCode($user, $validatedData['code'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный код 2FA'
                ], 422);
            }

            DB::beginTransaction();

            $this->twoFactorService->disableTwoFactor($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '2FA успешно отключена'
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
                'message' => 'Ошибка при отключении 2FA',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Сгенерировать новые коды восстановления
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'code' => 'required|string',
            ]);

            $user = $request->user();

            if (!$user->hasTwoFactorEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => '2FA не включена'
                ], 422);
            }

            // Проверить 2FA код
            if (!$this->twoFactorService->verifyLoginCode($user, $validatedData['code'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Неверный код 2FA'
                ], 422);
            }


            DB::beginTransaction();

            $newRecoveryCodes = $user->generateRecoveryCodes();
            $user->update(['two_factor_recovery_codes' => $newRecoveryCodes]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Коды восстановления сгенерированы',
                'recovery_codes' => $newRecoveryCodes
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
                'message' => 'Ошибка при генерации кодов восстановления',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику использования 2FA (только для администраторов)
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->twoFactorService->getTwoFactorStats();

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики 2FA',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
