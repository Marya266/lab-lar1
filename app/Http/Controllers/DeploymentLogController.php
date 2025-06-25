<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class DeploymentLogController extends Controller
{
    /**
     * Получить логи деплоя
     */
    public function getLogs(Request $request): JsonResponse
    {
        try {
            $lines = $request->get('lines', 50);
            $logFile = storage_path('logs/deployment.log');

            if (!File::exists($logFile)) {
                return response()->json([
                    'success' => true,
                    'logs' => [],
                    'message' => 'Файл логов еще не создан'
                ], 200);
            }

            // Получить последние N строк
            $command = "tail -{$lines} " . escapeshellarg($logFile);
            $output = shell_exec($command);

            $logs = $output ? explode("\n", trim($output)) : [];

            return response()->json([
                'success' => true,
                'logs' => $logs,
                'lines_count' => count($logs),
                'file_size' => File::size($logFile),
                'last_modified' => File::lastModified($logFile)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении логов',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Очистить логи деплоя
     */
    public function clearLogs(): JsonResponse
    {
        try {
            $logFile = storage_path('logs/deployment.log');

            if (File::exists($logFile)) {
                File::put($logFile, '');
                return response()->json([
                    'success' => true,
                    'message' => 'Логи деплоя очищены'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Файл логов не существует'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при очистке логов',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
