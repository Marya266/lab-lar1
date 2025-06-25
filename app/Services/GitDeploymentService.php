<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class GitDeploymentService
{
    private const LOCK_KEY = 'git_deployment_lock';
    private const LOCK_TIMEOUT = 300; // 5 минут

    /**
     * Выполнить обновление кода из Git
     */
    public function deploy(string $userIp): array
    {
        // Проверить блокировку
        if ($this->isDeploymentInProgress()) {
            return [
                'success' => false,
                'message' => 'Обновление уже выполняется. Пожалуйста, подождите.',
                'in_progress' => true
            ];
        }

        try {
            // Установить блокировку
            $this->setDeploymentLock();

            // Логировать начало деплоя
            $this->logDeploymentStart($userIp);

            $results = [];

            // 1. Переключиться на главную ветку
            $results['checkout'] = $this->checkoutMainBranch();

            // 2. Получить изменения из Git
            $results['fetch'] = $this->fetchFromGit();

            // 3. Проверить есть ли изменения
            $results['changes'] = $this->checkForChanges();

            // 4. Обновить проект до последней версии
            if ($results['changes']['has_changes']) {
                $results['pull'] = $this->pullFromGit();
                $results['composer'] = $this->updateComposerDependencies();
                $results['migrate'] = $this->runMigrations();
                $results['cache'] = $this->clearCache();
            } else {
                $results['pull'] = ['success' => true, 'message' => 'Обновления не требуются'];
            }

            // Логировать завершение
            $this->logDeploymentEnd($userIp, $results);

            return [
                'success' => true,
                'message' => 'Обновление кода завершено успешно',
                'details' => $this->sanitizeResults($results),
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            $this->logDeploymentError($userIp, $e);
            
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении кода: ' . $this->sanitizeString($e->getMessage()),
                'error' => $this->sanitizeString($e->getMessage())
            ];
        } finally {
            // Снять блокировку
            $this->releaseDeploymentLock();
        }
    }

    /**
     * Очистить строку от некорректных UTF-8 символов
     */
    private function sanitizeString(?string $string): string
    {
        if ($string === null) {
            return '';
        }

        // Конвертировать из Windows-1251 в UTF-8 если нужно
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'Windows-1251');
        }

        // Удалить некорректные UTF-8 символы
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // Удалить управляющие символы кроме переводов строк
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
        
        return trim($string);
    }

    /**
     * Очистить результаты от некорректных символов
     */
    private function sanitizeResults(array $results): array
    {
        array_walk_recursive($results, function (&$item) {
            if (is_string($item)) {
                $item = $this->sanitizeString($item);
            }
        });
        
        return $results;
    }


    /**
     * Выполнить команду с безопасной обработкой вывода
     */
    private function runCommand(string $command): array
    {
        try {
            // Установить локаль для корректной работы с UTF-8
            $fullCommand = 'chcp 65001 > nul 2>&1 && ' . $command;
            
            $result = Process::run($fullCommand);
            
            return [
                'success' => $result->successful(),
                'output' => $this->sanitizeString($result->output()),
                'error' => $this->sanitizeString($result->errorOutput()),
                'exit_code' => $result->exitCode()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $this->sanitizeString($e->getMessage()),
                'exit_code' => -1
            ];
        }
    }

    /**
     * Проверить выполняется ли обновление
     */
    private function isDeploymentInProgress(): bool
    {
        return Cache::has(self::LOCK_KEY);
    }

    /**
     * Установить блокировку деплоя
     */
    private function setDeploymentLock(): void
    {
        Cache::put(self::LOCK_KEY, [
            'started_at' => now()->toISOString(),
            'pid' => getmypid()
        ], self::LOCK_TIMEOUT);
    }

    /**
     * Снять блокировку деплоя
     */
    private function releaseDeploymentLock(): void
    {
        Cache::forget(self::LOCK_KEY);
    }

    /**
     * Переключиться на главную ветку
     */
    private function checkoutMainBranch(): array
    {
        $result = $this->runCommand('git checkout main');
        
        $this->logGitOperation('checkout_main', $result['output'], $result['error']);
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Переключение на главную ветку выполнено' : 'Ошибка переключения на главную ветку',
            'output' => $result['output'],
            'error' => $result['error']
        ];
    }

    /**
     * Получить изменения из Git
     */
    private function fetchFromGit(): array
    {
        $result = $this->runCommand('git fetch origin');
        
        $this->logGitOperation('fetch', $result['output'], $result['error']);
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Получение изменений из Git выполнено' : 'Ошибка получения изменений',
            'output' => $result['output'],
            'error' => $result['error']
        ];
    }

    /**
     * Проверить есть ли изменения
     */
    private function checkForChanges(): array
    {
        $result = $this->runCommand('git log HEAD..origin/main --oneline');
        
        $hasChanges = !empty(trim($result['output']));
        $changesCount = $hasChanges ? count(explode("\n", trim($result['output']))) : 0;
        
        $this->logGitOperation('check_changes', $result['output'], $result['error']);
        
        return [
            'success' => true,
            'has_changes' => $hasChanges,
            'changes_count' => $changesCount,
            'message' => $hasChanges ? "Найдено {$changesCount} новых коммитов" : 'Нет новых изменений',
            'changes' => $hasChanges ? explode("\n", trim($result['output'])) : []
        ];
    }

    /**
     * Обновить код из Git
     */
    private function pullFromGit(): array
    {
        $result = $this->runCommand('git pull origin main');
        
        $this->logGitOperation('pull', $result['output'], $result['error']);
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Обновление кода выполнено' : 'Ошибка обновления кода',
            'output' => $result['output'],
            'error' => $result['error']
        ];
    }


    /**
     * Обновить зависимости Composer
     */
    private function updateComposerDependencies(): array
    {
        $result = $this->runCommand('composer install --no-dev --optimize-autoloader --no-interaction');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Обновление зависимостей выполнено' : 'Ошибка обновления зависимостей',
            'output' => $result['output'],
            'error' => $result['error']
        ];
    }

    /**
     * Выполнить миграции
     */
    private function runMigrations(): array
    {
        $result = $this->runCommand('php artisan migrate --force');
        
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? 'Миграции выполнены' : 'Ошибка выполнения миграций',
            'output' => $result['output'],
            'error' => $result['error']
        ];
    }

    /**
     * Очистить кэш
     */
    private function clearCache(): array
    {
        $commands = [
            'php artisan config:clear',
            'php artisan route:clear',
            'php artisan view:clear',
            'php artisan cache:clear'
        ];

        $results = [];
        $allSuccess = true;

        foreach ($commands as $command) {
            $result = $this->runCommand($command);
            $results[] = [
                'command' => $command,
                'success' => $result['success'],
                'output' => $result['output'],
                'error' => $result['error']
            ];
            
            if (!$result['success']) {
                $allSuccess = false;
            }
        }

        return [
            'success' => $allSuccess,
            'message' => $allSuccess ? 'Очистка кэша выполнена' : 'Ошибки при очистке кэша',
            'details' => $results
        ];
    }

    /**
     * Логировать начало деплоя
     */
    private function logDeploymentStart(string $userIp): void
    {
        Log::channel('deployment')->info('=== НАЧАЛО АВТОМАТИЧЕСКОГО ОБНОВЛЕНИЯ ===', [
            'timestamp' => now()->toISOString(),
            'user_ip' => $userIp,
            'user_agent' => $this->sanitizeString(request()->userAgent() ?? 'Unknown'),
            'pid' => getmypid()
        ]);
    }

    /**
     * Логировать завершение деплоя
     */
    private function logDeploymentEnd(string $userIp, array $results): void
    {
        Log::channel('deployment')->info('=== ЗАВЕРШЕНИЕ АВТОМАТИЧЕСКОГО ОБНОВЛЕНИЯ ===', [
            'timestamp' => now()->toISOString(),
            'user_ip' => $userIp,
            'results' => $this->sanitizeResults($results),
            'success' => true
        ]);
    }

    /**
     * Логировать ошибку деплоя
     */
    private function logDeploymentError(string $userIp, \Exception $e): void
    {
        Log::channel('deployment')->error('=== ОШИБКА АВТОМАТИЧЕСКОГО ОБНОВЛЕНИЯ ===', [
            'timestamp' => now()->toISOString(),
            'user_ip' => $userIp,
            'error' => $this->sanitizeString($e->getMessage()),
            'trace' => $this->sanitizeString($e->getTraceAsString())
        ]);
    }

    /**
     * Логировать Git операцию
     */
    private function logGitOperation(string $operation, string $output, string $error = ''): void
    {
        Log::channel('deployment')->info("Git операция: {$operation}", [
            'operation' => $operation,
            'output' => $this->sanitizeString($output),
            'error' => $this->sanitizeString($error),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Получить статус деплоя
     */
    public function getDeploymentStatus(): array
    {
        if ($this->isDeploymentInProgress()) {
            $lockData = Cache::get(self::LOCK_KEY);
            return [
                'in_progress' => true,
                'started_at' => $lockData['started_at'] ?? null,
                'pid' => $lockData['pid'] ?? null
            ];
        }

        return ['in_progress' => false];
    }
}
