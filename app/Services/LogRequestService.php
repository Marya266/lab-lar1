<?php

namespace App\Services;

use App\Models\LogRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LogRequestService
{
    /**
     * Получить логи с фильтрацией и пагинацией
     */
    public function getLogs(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = LogRequest::with('user');

        // Фильтрация по методу
        if (!empty($filters['method'])) {
            $query->byMethod($filters['method']);
        }

        // Фильтрация по пользователю
        if (!empty($filters['user_id'])) {
            $query->byUser($filters['user_id']);
        }

        // Фильтрация по IP адресу
        if (!empty($filters['ip_address'])) {
            $query->byIp($filters['ip_address']);
        }

        // Фильтрация по статусу ответа
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Фильтрация по контроллеру
        if (!empty($filters['controller'])) {
            $query->byController($filters['controller']);
        }

        // Фильтрация по URL
        if (!empty($filters['url'])) {
            $query->where('url', 'like', '%' . $filters['url'] . '%');
        }

        // Фильтрация по периоду времени
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
        }

        // Только недавние (последние 72 часа) если не указан период
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $query->recent();
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Получить конкретный лог по ID
     */
    public function getLogById(int $id): ?LogRequest
    {
        return LogRequest::with('user')->find($id);
    }

    /**
     * Получить статистику логов
     */
    public function getStatistics(array $filters = []): array
    {
        $query = LogRequest::query();

        // Применить те же фильтры что и для основного запроса
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
        }

        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $query->recent();
        }

        // Общая статистика
        $totalRequests = $query->count();
        $successfulRequests = $query->whereBetween('response_status', [200, 299])->count();
        $errorRequests = $query->where('response_status', '>=', 400)->count();
        
        // Статистика по методам
        $methodStats = $query->select('method', DB::raw('COUNT(*) as count'))
                            ->groupBy('method')
                            ->pluck('count', 'method')
                            ->toArray();

        // Статистика по статусам
        $statusStats = $query->select('response_status', DB::raw('COUNT(*) as count'))
                            ->groupBy('response_status')
                            ->orderBy('response_status')
                            ->pluck('count', 'response_status')
                            ->toArray();

        // Топ URL
        $topUrls = $query->select('url', DB::raw('COUNT(*) as count'))
                        ->groupBy('url')
                        ->orderBy('count', 'desc')
                        ->limit(10)
                        ->pluck('count', 'url')
                        ->toArray();


        // Топ пользователи (авторизованные запросы)
        $topUsers = $query->whereNotNull('user_id')
                         ->select('user_id', DB::raw('COUNT(*) as count'))
                         ->with('user:id,name,email')
                         ->groupBy('user_id')
                         ->orderBy('count', 'desc')
                         ->limit(10)
                         ->get()
                         ->map(function ($item) {
                             return [
                                 'user_id' => $item->user_id,
                                 'user_name' => $item->user->name ?? 'Unknown',
                                 'user_email' => $item->user->email ?? 'Unknown',
                                 'count' => $item->count
                             ];
                         })
                         ->toArray();

        // Топ IP адреса
        $topIps = $query->select('ip_address', DB::raw('COUNT(*) as count'))
                       ->groupBy('ip_address')
                       ->orderBy('count', 'desc')
                       ->limit(10)
                       ->pluck('count', 'ip_address')
                       ->toArray();

        // Среднее время выполнения
        $avgExecutionTime = $query->avg('execution_time');

        // Запросы по часам (последние 24 часа)
        $hourlyStats = $query->where('created_at', '>=', now()->subHours(24))
                            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
                            ->groupBy(DB::raw('HOUR(created_at)'))
                            ->orderBy('hour')
                            ->pluck('count', 'hour')
                            ->toArray();

        return [
            'summary' => [
                'total_requests' => $totalRequests,
                'successful_requests' => $successfulRequests,
                'error_requests' => $errorRequests,
                'success_rate' => $totalRequests > 0 ? round(($successfulRequests / $totalRequests) * 100, 2) : 0,
                'avg_execution_time' => round($avgExecutionTime ?? 0, 3)
            ],
            'methods' => $methodStats,
            'statuses' => $statusStats,
            'top_urls' => $topUrls,
            'top_users' => $topUsers,
            'top_ips' => $topIps,
            'hourly_requests' => $hourlyStats
        ];
    }

    /**
     * Очистить старые логи (старше указанного количества часов)
     */
    public function cleanOldLogs(int $hoursToKeep = 72): int
    {
        return LogRequest::where('created_at', '<', now()->subHours($hoursToKeep))->delete();
    }

    /**
     * Получить логи для конкретного пользователя
     */
    public function getUserLogs(int $userId, int $perPage = 50): LengthAwarePaginator
    {
        return LogRequest::byUser($userId)
                        ->recent()
                        ->orderBy('created_at', 'desc')
                        ->paginate($perPage);
    }

    /**
     * Получить логи с ошибками
     */
    public function getErrorLogs(int $perPage = 50): LengthAwarePaginator
    {
        return LogRequest::where('response_status', '>=', 400)
                        ->with('user')
                        ->recent()
                        ->orderBy('created_at', 'desc')
                        ->paginate($perPage);
    }

    /**
     * Получить медленные запросы
     */
    public function getSlowRequests(float $minExecutionTime = 1.0, int $perPage = 50): LengthAwarePaginator
    {
        return LogRequest::where('execution_time', '>=', $minExecutionTime)
                        ->with('user')
                        ->recent()
                        ->orderBy('execution_time', 'desc')
                        ->paginate($perPage);
    }
}
