<?php

namespace App\DTOs;

class ChangeLogDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getId(): ?int
    {
        return $this->data['id'] ?? null;
    }

    public function getEntityType(): string
    {
        return $this->data['entity_type'];
    }

    public function getEntityId(): int
    {
        return $this->data['entity_id'];
    }

    public function getUserId(): int
    {
        return $this->data['user_id'];
    }

    public function getAction(): string
    {
        return $this->data['action'];
    }

    public function getOldValues(): ?array
    {
        return $this->data['old_values'] ?? null;
    }

    public function getNewValues(): ?array
    {
        return $this->data['new_values'] ?? null;
    }

    public function getCreatedAt(): ?string
    {
        return $this->data['created_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'entity_type' => $this->getEntityType(),
            'entity_id' => $this->getEntityId(),
            'user_id' => $this->getUserId(),
            'user' => $this->data['user'] ?? null,
            'action' => $this->getAction(),
            'old_values' => $this->getOldValues(),
            'new_values' => $this->getNewValues(),
            'created_at' => $this->getCreatedAt(),
        ];
    }

    public static function fromModel($changeLog): self
    {
        $data = $changeLog->toArray();
        if ($changeLog->relationLoaded('user')) {
            $data['user'] = $changeLog->user;
        }
        return new self($data);
    }

    public function toCreateArray(): array
    {
        return [
            'entity_type' => $this->getEntityType(),
            'entity_id' => $this->getEntityId(),
            'user_id' => $this->getUserId(),
            'action' => $this->getAction(),
            'old_values' => $this->getOldValues(),
            'new_values' => $this->getNewValues(),
        ];
    }
}
