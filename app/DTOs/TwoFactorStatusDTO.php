<?php

namespace App\DTOs;

class TwoFactorStatusDTO
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function isEnabled(): bool
    {
        return $this->data['enabled'];
    }

    public function getRecoveryCodesCount(): int
    {
        return $this->data['recovery_codes_count'] ?? 0;
    }

    public function getEnabledAt(): ?string
    {
        return $this->data['enabled_at'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'recovery_codes_count' => $this->getRecoveryCodesCount(),
            'enabled_at' => $this->getEnabledAt(),
            'recommendations' => $this->isEnabled() ? [
                'Сохраните коды восстановления в безопасном месте',
                'Регулярно проверяйте работу 2FA',
                'Не делитесь кодами восстановления с другими'
            ] : [
                'Включите двухфакторную аутентификацию для повышения безопасности',
                'Используйте приложение Google Authenticator или аналогичное'
            ]
        ];
    }
}
