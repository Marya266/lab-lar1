<?php

namespace App\DTOs;

class ClientDataDTO
{
   private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function toArray(): array
    {
        return [
            'client_ip' => $this->data['ip'] ?? null,
            'user_agent' => $this->data['user_agent'] ?? null,
            'request_method' => $this->data['method'] ?? null,
            'request_url' => $this->data['url'] ?? null,
            'headers' => $this->data['headers'] ?? [],
            'parameters' => $this->data['parameters'] ?? [],
            'timestamp' => now()->toISOString()
        ];
    }
}
