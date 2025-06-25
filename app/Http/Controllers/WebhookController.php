<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLogReportJob;
use App\Models\Messenger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WebhookController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function telegramWebhook(Request $request)
    {
        $data = $request->all();
        
        Log::info('Telegram webhook received', $data);

        if (!isset($data['message'])) {
            return response()->json(['ok' => true]);
        }

        $message = $data['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'Unknown';

        // Проверяем, что это команда
        if (!str_starts_with($text, '/')) {
            return response()->json(['ok' => true]);
        }

        $response = $this->handleCommand($text, $chatId, $username);
        
        if ($response) {
            $this->sendTelegramMessage($chatId, $response);
        }

        return response()->json(['ok' => true]);
    }

    private function handleCommand(string $command, string $chatId, string $username): ?string
    {
        $parts = explode(' ', trim($command));
        $cmd = strtolower($parts[0]);

        switch ($cmd) {
            case '/start':
                return "🤖 Добро пожаловать, {$username}!\n\n" .
                       "Доступные команды:\n" .
                       "/logs - Получить отчёт с логами\n" .
                       "/reports - Список доступных отчётов\n" .
                       "/status - Статус системы\n" .
                       "/help - Справка";

            case '/help':
                return "📖 Справка по командам:\n\n" .
                       "/logs [тип] - Генерация отчёта с логами\n" .
                       "  Типы: all, errors, info, debug\n" .
                       "  Пример: /logs errors\n\n" .
                       "/reports - Показать доступные отчёты\n" .
                       "/status - Статистика системы\n" .
                       "/start - Начать работу";

            case '/logs':
                $logType = $parts[1] ?? 'all';
                return $this->generateLogReport($chatId, $logType, $username);

            case '/reports':
                return $this->getAvailableReports();

            case '/status':
                return $this->getSystemStatus();

            default:
                return "❌ Неизвестная команда: {$cmd}\n\nИспользуйте /help для получения справки.";
        }
    }

    private function generateLogReport(string $chatId, string $logType, string $username): string
    {
        try {
            // Запускаем Job для генерации отчёта в фоне
            GenerateLogReportJob::dispatch($chatId, $logType, $username);
            
            return "📊 Генерация отчёта с логами запущена!\n" .
                   "Тип: {$logType}\n" .
                   "Администратор: {$username}\n\n" .
                   "⏳ Пожалуйста, подождите. Отчёт будет отправлен в этот чат после завершения.";
                   
        } catch (\Exception $e) {
            Log::error('Error generating log report', [
                'chat_id' => $chatId,
                'log_type' => $logType,
                'error' => $e->getMessage()
            ]);
            
            return "❌ Ошибка при генерации отчёта: " . $e->getMessage();
        }
    }


    private function getAvailableReports(): string
    {
        $reports = collect(Storage::disk('local')->files('reports'))
                  ->filter(fn($file) => str_contains($file, '.'))
                  ->take(10)
                  ->map(function($file) {
                      $info = pathinfo($file);
                      $size = Storage::disk('local')->size($file);
                      $date = date('d.m.Y H:i', Storage::disk('local')->lastModified($file));
                      return "📄 {$info['basename']} ({$this->formatBytes($size)}, {$date})";
                  })
                  ->join("\n");

        return $reports ? 
               "📋 Последние отчёты:\n\n{$reports}" : 
               "📭 Отчёты не найдены";
    }

    private function getSystemStatus(): string
    {
        $messengers = Messenger::forEnvironment()->count();
        $totalReports = count(Storage::disk('local')->files('reports'));
        $logSize = $this->getLogFileSize();
        $queueJobs = $this->getQueueJobsCount();

        return "🖥 Статус системы:\n\n" .
               "🔗 Активных мессенджеров: {$messengers}\n" .
               "📊 Всего отчётов: {$totalReports}\n" .
               "📝 Размер логов: {$logSize}\n" .
               "⚡️ Задач в очереди: {$queueJobs}\n" .
               "🌍 Окружение: " . app()->environment() . "\n" .
               "📅 Время: " . now()->format('d.m.Y H:i:s');
    }

    private function sendTelegramMessage(string $chatId, string $text): void
    {
        $messenger = Messenger::where('name', 'Telegram')
                             ->forEnvironment()
                             ->first();
        
        if ($messenger) {
            $this->notificationService->sendToMessenger($messenger, $chatId, $text);
        }
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

    private function getLogFileSize(): string
    {
        $logPath = storage_path('logs/laravel.log');
        return file_exists($logPath) ? 
               $this->formatBytes(filesize($logPath)) : 
               'N/A';
    }

    private function getQueueJobsCount(): int
    {
        try {
            return \DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
