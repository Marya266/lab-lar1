<?php

namespace App\Http\Controllers;

use App\Services\GitDeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GitWebhookController extends Controller
{
    private GitDeploymentService $deploymentService;

    public function __construct(GitDeploymentService $deploymentService)
    {
        $this->deploymentService = $deploymentService;
    }

public function handleWebhook(Request $request): JsonResponse
{
    try {
        // Получить IP адрес пользователя
        $userIp = $request->ip();
        
        // Безопасно получить user agent
        $userAgent = $request->userAgent();
        $safeUserAgent = $userAgent ? mb_convert_encoding($userAgent, 'UTF-8', 'UTF-8') : 'Unknown';
        
        // Логировать входящий запрос
        Log::channel('deployment')->info('Входящий webhook запрос', [
            'ip' => $userIp,
            'user_agent' => $safeUserAgent,
            'timestamp' => now()->toISOString(),
            'has_secret' => $request->has('secret_key')
        ]);

        // Проверить наличие секретного ключа
        if (!$request->has('secret_key')) {
            Log::channel('deployment')->warning('Отклонен запрос без секретного ключа', [
                'ip' => $userIp,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Секретный ключ не предоставлен'
            ], 400);
        }

        // Получить секретный ключ из запроса и конфигурации
        $providedSecret = $request->input('secret_key');
        $configSecret = config('app.git_webhook_secret', env('GIT_WEBHOOK_SECRET'));

        // Проверить наличие конфигурационного ключа
        if (empty($configSecret)) {
            Log::channel('deployment')->error('Секретный ключ не настроен в конфигурации', [
                'ip' => $userIp,
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Сервер не настроен для webhook'
            ], 500);
        }

        // Валидация секретного ключа (чувствительна к регистру)
        if (!hash_equals($configSecret, $providedSecret)) {
            Log::channel('deployment')->warning('Отклонен запрос с неверным секретным ключом', [
                'ip' => $userIp,
                'provided_secret_length' => strlen($providedSecret),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Неверный секретный ключ'
            ], 403);
        }

        // Выполнить обновление кода
        $deploymentResult = $this->deploymentService->deploy($userIp);

        // Убедиться что результат содержит только валидные UTF-8 строки
        $safeResult = $this->sanitizeJsonResponse($deploymentResult);

        // Вернуть результат
        return response()->json($safeResult, $safeResult['success'] ? 200 : 500);

    } catch (\Exception $e) {
        $safeError = mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8');
        
        Log::channel('deployment')->error('Критическая ошибка в webhook обработчике', [
            'ip' => $request->ip(),
            'error' => $safeError,
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Внутренняя ошибка сервера при обработке webhook',
            'error' => $safeError
        ], 500);
    }
}

/**
 * Очистить ответ от некорректных UTF-8 символов
 */
private function sanitizeJsonResponse(array $data): array
{
    array_walk_recursive($data, function (&$item) {
        if (is_string($item)) {
            // Конвертировать в UTF-8 если нужно
            if (!mb_check_encoding($item, 'UTF-8')) {
                $item = mb_convert_encoding($item, 'UTF-8', 'Windows-1251');
            }
            // Убрать некорректные символы
            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            // Убрать управляющие символы
            $item = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $item);
        }
    });
    
    return $data;
}

    /**
     * Получить статус деплоя
     */
    public function getStatus(): JsonResponse
    {
        try {
            $status = $this->deploymentService->getDeploymentStatus();
            
            return response()->json([
                'success' => true,
                'status' => $status
            ], 200);
        

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка получения статуса деплоя',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
