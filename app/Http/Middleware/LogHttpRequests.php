<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use App\Models\LogRequest;
use App\DTOs\LogRequestDTO;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class LogHttpRequests
{
    /**
     * Handle an incoming request and log it.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $startTime = microtime(true);
        
        // Получить ответ
        $response = $next($request);
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 3);

        // Логировать запрос асинхронно чтобы не замедлять ответ
        $this->logRequest($request, $response, $executionTime);

        return $response;
    }

    /**
     * Логировать HTTP запрос
     */
    private function logRequest(Request $request, SymfonyResponse $response, float $executionTime): void
    {
        try {
            // Получить информацию о маршруте
            $route = Route::current();
            $routeName = $route ? $route->getName() : null;
            $action = $route ? $route->getAction() : [];

            // Получить контроллер и метод
            $controllerClass = null;
            $controllerMethod = null;
            
            if (isset($action['controller'])) {
                $parts = explode('@', $action['controller']);
                $controllerClass = $parts[0] ?? null;
                $controllerMethod = $parts[1] ?? null;
            }

            // Подготовить данные для логирования
            $logData = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $routeName,
                'controller_class' => $controllerClass,
                'controller_method' => $controllerMethod,
                'request_body' => $this->getRequestBody($request),
                'request_headers' => $this->getFilteredHeaders($request->headers->all()),
                'user_id' => Auth::id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'response_status' => $response->getStatusCode(),
                'response_body' => $this->getResponseBody($response),
                'response_headers' => $this->getFilteredHeaders($response->headers->all()),
                'execution_time' => $executionTime
            ];

            // Создать DTO и сохранить лог
            $dto = new LogRequestDTO($logData);
            LogRequest::create($dto->toCreateArray());

        } catch (\Exception $e) {
            // Не прерывать выполнение если логирование не удалось
            \Log::error('Ошибка при логировании HTTP запроса', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method()
            ]);
        }
    }

    /**
     * Получить тело запроса
     */
    private function getRequestBody(Request $request): ?string
    {
        $content = $request->getContent();
        
        // Не логировать слишком большие запросы (больше 10KB)
        if (strlen($content) > 10240) {
            return '[СЛИШКОМ БОЛЬШОЕ ТЕЛО ЗАПРОСА - ' . strlen($content) . ' байт]';
        }

        // Не логировать файлы
        if ($request->hasFile('file') || $request->hasFile('image') || $request->hasFile('document')) {
            return '[ФАЙЛОВАЯ ЗАГРУЗКА]';
        }

        // Фильтрация чувствительных данных
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret', 'key'];
        
        if ($request->isJson()) {
            $data = $request->json()->all();
            foreach ($sensitiveFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = '[СКРЫТО]';
                }
            }
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }


        // Для form-data запросов
        $data = $request->all();
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[СКРЫТО]';
            }
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Получить тело ответа
     */
    private function getResponseBody(SymfonyResponse $response): ?string
    {
        $content = $response->getContent();
        
        // Не логировать слишком большие ответы (больше 50KB)
        if (strlen($content) > 51200) {
            return '[СЛИШКОМ БОЛЬШОЙ ОТВЕТ - ' . strlen($content) . ' байт]';
        }

        // Не логировать бинарные данные
        if (!mb_check_encoding($content, 'UTF-8')) {
            return '[БИНАРНЫЕ ДАННЫЕ]';
        }

        // Для JSON ответов попробовать сократить чувствительную информацию
        if ($response->headers->get('Content-Type', '') === 'application/json') {
            $data = json_decode($content, true);
            if (is_array($data)) {
                // Скрыть токены и пароли в ответе
                if (isset($data['access_token'])) {
                    $data['access_token'] = '[СКРЫТО]';
                }
                if (isset($data['recovery_codes'])) {
                    $data['recovery_codes'] = '[СКРЫТО]';
                }
                return json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        }

        return $content;
    }

    /**
     * Фильтровать заголовки
     */
    private function getFilteredHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'set-cookie',
            'x-api-key',
            'x-auth-token'
        ];

        $filtered = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $filtered[$key] = '[СКРЫТО]';
            } else {
                $filtered[$key] = is_array($value) ? $value : [$value];
            }
        }

        return $filtered;
    }
}
