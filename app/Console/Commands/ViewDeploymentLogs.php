<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ViewDeploymentLogs extends Command
{
    protected $signature = 'logs:deployment {--lines=50 : Number of lines to show}';
    protected $description = 'View deployment logs';

    public function handle(): int
    {
        $logFile = storage_path('logs/deployment.log');
        $lines = $this->option('lines');

        if (!File::exists($logFile)) {
            $this->error('Файл логов деплоя не найден: ' . $logFile);
            return Command::FAILURE;
        }

        $this->info("Последние {$lines} строк логов деплоя:");
        $this->line('==========================================');

        $command = "tail -{$lines} " . escapeshellarg($logFile);
        $output = shell_exec($command);

        if ($output) {
            $this->line($output);
        } else {
            $this->warn('Логи пусты или не удалось прочитать файл');
        }

        return Command::SUCCESS;
    }
}
