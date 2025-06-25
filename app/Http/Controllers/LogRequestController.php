<?php

namespace App\Http\Controllers;

use App\Models\LogRequest;
use App\DTOs\LogRequestCollectionDTO;
use App\DTOs\LogRequestDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LogRequestController extends Controller
{
    /**
     * Получить все логи запросов с фильтрацией
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Валидация параметров запроса
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|integer|exists:users,id',
                'method' => 'nullable|string|in:GET,POST,PUT,PATCH,DELETE',
                'response_status' => 'nullable|integer|min:100|max:599',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'route_name' => 'nullable|string',
                'ip_address' => 'nullable|ip',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Построение запроса с фильтрами
            $query = LogRequest::with('user:id,name,email');

            // Фильтр по пользователю
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Фильтр по методу
            if ($request->filled('method')) {
                $query->where('method', $request->method);
            }

            // Фильтр по статус коду
            if ($request->filled('response_status')) {
                $query->where('response_status', $request->response_status);
            }

            // Фильтр по дате
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Фильтр по названию роута
            if ($request->filled('route_name')) {
                $query->where('route_name', 'like', '%' . $request->route_name . '%');
            }

            // Фильтр по IP адресу
            if ($request->filled('ip_address')) {
                $query->where('ip_address', $request->ip_address);
            }

            // Сортировка по дате (новые сначала)
            $query->orderBy('created_at', 'desc');

            // Пагинация
            $perPage = $request->get('per_page', 15);
            $logs = $query->paginate($perPage);

            // Преобразование в DTO
            $logDTOs = $logs->getCollection()->map(function ($log) {
                return new LogRequestDTO([
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user_name' => $log->user ? $log->user->name : null,
                    'method' => $log->method,
                    'url' => $log->url,
                    'route_name' => $log->route_name,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'response_status' => $log->response_status,
                    'request_body' => $log->request_body,
                    'response_body' => $log->response_body,
                    'execution_time' => $log->execution_time,
                    'created_at' => $log->created_at->toISOString()
                ]);
            });


            $collection = new LogRequestCollectionDTO([
                'logs' => $logDTOs->toArray(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Логи запросов получены',
                'data' => $collection->toArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении логов запросов',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику по логам
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Валидация параметров
            $validator = Validator::make($request->all(), [
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Базовый запрос для фильтрации по датам
            $baseQuery = LogRequest::query();

            // Фильтр по дате
            if ($request->filled('date_from')) {
                $baseQuery->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $baseQuery->whereDate('created_at', '<=', $request->date_to);
            }

            // Общая статистика (клонируем базовый запрос для каждой метрики)
            $totalRequests = (clone $baseQuery)->count();
            $uniqueUsers = (clone $baseQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
            $uniqueIPs = (clone $baseQuery)->distinct('ip_address')->count('ip_address');
            $avgExecutionTime = (clone $baseQuery)->avg('execution_time');

            // Статистика по методам
            $methodStats = (clone $baseQuery)
                ->groupBy('method')
                ->selectRaw('method, count(*) as count')
                ->pluck('count', 'method')
                ->toArray();

            // Статистика по статус кодам
            $statusStats = (clone $baseQuery)
                ->groupBy('response_status')
                ->selectRaw('response_status, count(*) as count')
                ->orderBy('response_status')
                ->pluck('count', 'response_status')
                ->toArray();

            // Топ 10 самых посещаемых роутов
            $topRoutes = (clone $baseQuery)
                ->whereNotNull('route_name')
                ->groupBy('route_name')
                ->selectRaw('route_name, count(*) as count')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'route_name')
                ->toArray();

            // Топ 10 самых активных IP адресов
            $topIPs = (clone $baseQuery)
                ->groupBy('ip_address')
                ->selectRaw('ip_address, count(*) as count')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'ip_address')
                ->toArray();

            // Статистика по дням (последние 7 дней)
            $dailyStats = LogRequest::selectRaw('DATE(created_at) as date, count(*) as count')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->pluck('count', 'date')
                ->toArray();


            return response()->json([
                'success' => true,
                'message' => 'Статистика получена',
                'data' => [
                    'general' => [
                        'total_requests' => $totalRequests,
                        'unique_users' => $uniqueUsers,
                        'unique_ips' => $uniqueIPs,
                        'avg_execution_time' => round($avgExecutionTime ?? 0, 2)
                    ],
                    'methods' => $methodStats,
                    'statuses' => $statusStats,
                    'top_routes' => $topRoutes,
                    'top_ips' => $topIPs,
                    'daily_stats' => $dailyStats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить подробную информацию о логе
     */
    public function show(int $id): JsonResponse
    {
        try {
            $log = LogRequest::with('user:id,name,email')->findOrFail($id);

            $logDTO = new LogRequestDTO([
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user ? $log->user->name : null,
                'method' => $log->method,
                'url' => $log->url,
                'route_name' => $log->route_name,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'response_status' => $log->response_status,
                'request_body' => $log->request_body,
                'response_body' => $log->response_body,
                'execution_time' => $log->execution_time,
                'created_at' => $log->created_at->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Лог запроса получен',
                'data' => $logDTO->toArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении лога',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Очистить старые логи
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            // Валидация параметров
            $validator = Validator::make($request->all(), [
                'days' => 'required|integer|min:1|max:365',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors()
                ], 422);
            }

            $days = $request->input('days');
            $cutoffDate = Carbon::now()->subDays($days);

            // Подсчитать количество записей для удаления
            $count = LogRequest::where('created_at', '<', $cutoffDate)->count();

            // Удалить старые логи
            LogRequest::where('created_at', '<', $cutoffDate)->delete();

            return response()->json([
                'success' => true,
                'message' => "Очистка выполнена успешно",
                'data' => [
                    'deleted_count' => $count,
                    'cutoff_date' => $cutoffDate->toDateString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при очистке логов',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
