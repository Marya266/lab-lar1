<?php

namespace App\Http\Controllers;

use App\Services\ChangeLogService;
use App\DTOs\ChangeLogCollectionDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChangeLogController extends Controller
{
    private ChangeLogService $changeLogService;

    public function __construct(ChangeLogService $changeLogService)
    {
        $this->changeLogService = $changeLogService;
    }

    /**
     * Получить все логи с фильтрацией
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $entityType = $request->get('entity_type');
            $entityId = $request->get('entity_id');
            $userId = $request->get('user_id');

            if ($entityType && $entityId) {
                $logs = $this->changeLogService->getEntityLogs($entityType, $entityId, $perPage);
            } elseif ($entityType) {
                $logs = $this->changeLogService->getEntityTypeLogs($entityType, $perPage);
            } elseif ($userId) {
                $logs = $this->changeLogService->getUserLogs($userId, $perPage);
            } else {
                $logs = \App\Models\ChangeLog::with('user')
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
            }

            $collection = ChangeLogCollectionDTO::fromCollection($logs);

            return response()->json([
                'success' => true,
                'data' => $collection->toArray()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении логов изменений',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить логи конкретной сущности
     */
    public function getEntityHistory(Request $request, string $entityType, int $entityId): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $logs = $this->changeLogService->getEntityLogs($entityType, $entityId, $perPage);
            $collection = ChangeLogCollectionDTO::fromCollection($logs);

            return response()->json([
                'success' => true,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'data' => $collection->toArray()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении истории изменений сущности',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить статистику изменений
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_changes' => \App\Models\ChangeLog::count(),
                'changes_by_action' => \App\Models\ChangeLog::selectRaw('action, COUNT(*) as count')
                    ->groupBy('action')
                    ->pluck('count', 'action'),
                'changes_by_entity' => \App\Models\ChangeLog::selectRaw('entity_type, COUNT(*) as count')
                    ->groupBy('entity_type')
                    ->pluck('count', 'entity_type'),
                'recent_changes' => \App\Models\ChangeLog::with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($log) {
                        return \App\DTOs\ChangeLogDTO::fromModel($log)->toArray();
                    })
            ];

            return response()->json([
                'success' => true,
                'statistics' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении статистики',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
