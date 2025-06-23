<?php

namespace App\DTOs;

class DatabaseInfoDTO
{
        private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function toArray(): array
    {
        return [
            'database_driver' => $this->data['driver'] ?? null,
            'database_name' => $this->data['database'] ?? null,
            'database_version' => $this->data['version'] ?? null,
            'charset' => $this->data['charset'] ?? null,
            'tables_count' => $this->data['tables_count'] ?? 0,
            'error' => $this->data['error'] ?? null,
            'timestamp' => now()->toISOString()
        ];
    }

}
