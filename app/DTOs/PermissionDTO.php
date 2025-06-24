<?php

namespace App\DTOs;

class PermissionDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getName(): string
    {
        return $this->data['name'];
    }

    public function getDescription(): ?string
    {
        return $this->data['description'] ?? null;
    }

    public function getCode(): string
    {
        return $this->data['code'];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->data['id'] ?? null,
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'code' => $this->getCode(),
            'created_at' => $this->data['created_at'] ?? null,
            'updated_at' => $this->data['updated_at'] ?? null,
            'deleted_at' => $this->data['deleted_at'] ?? null,
        ];
    }

    public static function fromModel($permission): self
    {
        return new self($permission->toArray());
    }

    public function toCreateArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'code' => $this->getCode(),
        ];
    }
}
