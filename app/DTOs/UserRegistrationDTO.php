<?php

namespace App\DTOs;

class UserRegistrationDTO
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
            'name' => $this->getName(),
            'email' => $this->getEmail(),
            'password' => $this->getPassword()
        ];
    }
    
    public function toResource(): UserResourceDTO
    {
        return new UserResourceDTO([
            'name' => $this->getName(),
            'email' => $this->getEmail()
        ]);
    }

}
