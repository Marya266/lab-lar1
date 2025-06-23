<?php

namespace App\DTOs;

class PhpInfoDTO
{
   private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function toArray(): array
    {
        return [
            'php_version' => $this->data['version'] ?? null,
            'loaded_extensions' => $this->data['extensions'] ?? [],
            'server_info' => $this->data['server_info'] ?? null,
            'timestamp' => now()->toISOString()
        ];
    }

}
