<?php

namespace App\DTOs;

class TwoFactorSetupDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getSecret(): string
    {
        return $this->data['secret'];
    }

    public function getQrCodeUrl(): string
    {
        return $this->data['qr_code_url'];
    }

    public function getManualEntryKey(): string
    {
        return $this->data['manual_entry_key'];
    }

    public function toArray(): array
    {
        return [
            'secret' => $this->getSecret(),
            'qr_code_url' => $this->getQrCodeUrl(),
            'manual_entry_key' => $this->getManualEntryKey(),
            'instructions' => [
                'step1' => 'Установите приложение Google Authenticator на ваш телефон',
                'step2' => 'Отсканируйте QR-код или введите ключ вручную',
                'step3' => 'Введите 6-значный код из приложения для подтверждения'
            ]
        ];
    }
}
