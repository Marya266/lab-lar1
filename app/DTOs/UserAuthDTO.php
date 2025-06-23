<?php

namespace App\DTOs;

class UserAuthDTO
{
    private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function getEmail(): string
    {
        return $this->data['email'];
    }
    
    public function getPassword(): string
    {
        return $this->data['password'];
    }
    
    public function toArray(): array
    {
        return [
            'email' => $this->getEmail(),
            'password' => $this->getPassword()
        ];
    }
    
    public function toResource(): UserResourceDTO
    {
        return new UserResourceDTO($this->data);
    }

}
