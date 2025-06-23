<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\DTOs\UserAuthDTO;


class UserAuthRequest extends FormRequest
{
     public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ];
    }
    
    public function messages(): array
    {
        return [
            'email.required' => 'Email обязателен для заполнения',
            'email.email' => 'Неверный формат email',
            'password.required' => 'Пароль обязателен для заполнения',
        ];
    }
    
    public function toDTO(): UserAuthDTO
    {
        return new UserAuthDTO($this->validated());
    }

}
