<?php

namespace App\DTOs;

class UserResourceDTO
{
    private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->data['id'] ?? null,
            'name' => $this->data['name'] ?? null,
            'email' => $this->data['email'] ?? null,
            'email_verified_at' => $this->data['email_verified_at'] ?? null,
            'created_at' => $this->data['created_at'] ?? null,
            'updated_at' => $this->data['updated_at'] ?? null,
        ];
    }

}
