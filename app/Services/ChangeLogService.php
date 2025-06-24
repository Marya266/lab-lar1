<?php

namespace App\Services;

use App\Models\ChangeLog;
use App\DTOs\ChangeLogDTO;
use Illuminate\Database\Eloquent\Model;

class ChangeLogService
{
    /**
     * Логирование создания сущности
     */
    public function logCreated(Model $entity, int $userId): ChangeLog
    {
        return $this->createLog($entity, $userId, 'created', null, $entity->getAttributes());
    }

    /**
     * Логирование обновления сущности
     */
    public function logUpdated(Model $entity, array $oldValues, int $userId): ChangeLog
    {
        return $this->createLog($entity, $userId, 'updated', $oldValues, $entity->getAttributes());
    }

    /**
     * Логирование удаления сущности
     */
    public function logDeleted(Model $entity, int $userId): ChangeLog
    {
        return $this->createLog($entity, $userId, 'deleted', $entity->getAttributes(), null);
    }

    /**
     * Создать запись в логе
     */
    private function createLog(Model $entity, int $userId, string $action, ?array $oldValues, ?array $newValues): ChangeLog
    {
        $logData = [
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->id,
            'user_id' => $userId,
            'action' => $action,
            'old_values' => $this->filterSensitiveData($oldValues),
            'new_values' => $this->filterSensitiveData($newValues)
        ];

        $dto = new ChangeLogDTO($logData);
        return ChangeLog::create($dto->toCreateArray());
    }

    /**
     * Фильтрация чувствительных данных (пароли и т.д.)
     */
    private function filterSensitiveData(?array $data): ?array
    {
        if (!$data) {
            return null;
        }

        $sensitiveFields = ['password', 'password_confirmation', 'remember_token'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[СКРЫТО]';
            }
        }

        return $data;
    }

    /**
     * Получить логи для конкретной сущности
     */
    public function getEntityLogs(string $entityType, int $entityId, int $perPage = 15)
    {
        return ChangeLog::with('user')
            ->forSpecificEntity($entityType, $entityId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Получить логи по типу сущности
     */
    public function getEntityTypeLogs(string $entityType, int $perPage = 15)
    {
        return ChangeLog::with('user')
            ->forEntity($entityType)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Получить все логи пользователя
     */
    public function getUserLogs(int $userId, int $perPage = 15)
    {
        return ChangeLog::with('user')
            ->byUser($userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
