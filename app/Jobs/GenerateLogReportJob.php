<?php

namespace App\Jobs;

use App\Models\Messenger;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateLogReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chatId;
    protected $logType;
    protected $username;

    public function __construct(string $chatId, string $logType, string $username)
    {
        $this->chatId = $chatId;
        $this->logType = $logType;
        $this->username = $username;
    }

    public function handle(NotificationService $notificationService)
    {
        $startTime = microtime(true);
        
        try {
            $reportData = $this->generateReport();
            $filename = $this->saveReport($reportData);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Отправляем уведомление о готовности отчёта
            $this->sendReportNotification($notificationService, $filename, $executionTime);
            
            Log::info('Log report generated successfully', [
                'chat_id' => $this->chatId,
                'log_type' => $this->logType,
                'username' => $this->username,
                'filename' => $filename,
                'execution_time_ms' => $executionTime
            ]);
            
        } catch (\Exception $e) {
            $this->sendErrorNotification($notificationService, $e->getMessage());
            
            Log::error('Error generating log report', [
                'chat_id' => $this->chatId,
                'log_type' => $this->logType,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function generateReport(): array
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!file_exists($logPath)) {
            throw new \Exception('Файл логов не найден');
        }
        
        $logs = $this->parseLogFile($logPath);
        $filteredLogs = $this->filterLogsByType($logs, $this->logType);
        
        return [
            'summary' => $this->generateSummary($filteredLogs),
            'logs' => $filteredLogs,
            'metadata' => [
                'generated_at' => now()->toISOString(),
                'generated_by' => $this->username,
                'chat_id' => $this->chatId,
                'log_type' => $this->logType,
                'total_entries' => count($filteredLogs),
                'file_size' => filesize($logPath)
            ]
        ];
    }

    private function parseLogFile(string $logPath): array
    {
        $logs = [];
        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Берём последние 1000 строк для производительности
        $lines = array_slice($lines, -1000);
        
        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)/', $line, $matches)) {
                $logs[] = [
                    'timestamp' => $matches[1],
                    'level' => strtolower($matches[2]),
                    'message' => $matches[3],
                    'raw' => $line
                ];
            }
        }
        
        return array_reverse($logs); // Новые записи сверху
    }

    private function filterLogsByType(array $logs, string $type): array
    {
        if ($type === 'all') {
            return $logs;
        }
        
        return array_filter($logs, function($log) use ($type) {
            return $log['level'] === strtolower($type);
        });
    }


    private function generateSummary(array $logs): array
    {
        $summary = [
            'total' => count($logs),
            'by_level' => [],
            'recent_errors' => [],
            'time_range' => []
        ];
        
        foreach ($logs as $log) {
            $level = $log['level'];
            $summary['by_level'][$level] = ($summary['by_level'][$level] ?? 0) + 1;
            
            if (in_array($level, ['error', 'critical', 'emergency']) && count($summary['recent_errors']) < 5) {
                $summary['recent_errors'][] = [
                    'timestamp' => $log['timestamp'],
                    'message' => substr($log['message'], 0, 100) . '...'
                ];
            }
        }
        
        if (!empty($logs)) {
            $summary['time_range'] = [
                'from' => end($logs)['timestamp'],
                'to' => $logs[0]['timestamp']
            ];
        }
        
        return $summary;
    }

    private function saveReport(array $reportData): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "log_reports/log_report_{$this->logType}_{$timestamp}.json";
        
        $content = json_encode($reportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        Storage::disk('local')->put($filename, $content);
        
        return $filename;
    }

    private function sendReportNotification(NotificationService $notificationService, string $filename, float $executionTime): void
    {
        $messenger = Messenger::where('name', 'Telegram')->forEnvironment()->first();
        
        if (!$messenger) {
            return;
        }
        
        $fileInfo = pathinfo($filename);
        $fileSize = Storage::disk('local')->size($filename);
        
        $message = "✅ Отчёт с логами готов!\n\n" .
                   "📄 Файл: {$fileInfo['basename']}\n" .
                   "📊 Тип: {$this->logType}\n" .
                   "📏 Размер: " . $this->formatBytes($fileSize) . "\n" .
                   "⏱️ Время генерации: {$executionTime}ms\n" .
                   "👤 Запросил: {$this->username}\n\n" .
                   "🔗 Файл сохранён в: storage/app/{$filename}";
        
        $notificationService->sendToMessenger($messenger, $this->chatId, $message);
    }

    private function sendErrorNotification(NotificationService $notificationService, string $error): void
    {
        $messenger = Messenger::where('name', 'Telegram')->forEnvironment()->first();
        
        if (!$messenger) {
            return;
        }
        
        $message = "❌ Ошибка при генерации отчёта!\n\n" .
                   "🔍 Тип: {$this->logType}\n" .
                   "👤 Запросил: {$this->username}\n" .
                   "💥 Ошибка: {$error}";
        
        $notificationService->sendToMessenger($messenger, $this->chatId, $message);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
