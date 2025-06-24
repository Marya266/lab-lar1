<?php

namespace App\Services;

use App\Models\User;
use App\Models\TwoFactorConfirmation;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TwoFactorService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Сгенерировать секретный ключ для 2FA
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Получить QR код URL для настройки 2FA
     */
    public function getQrCodeUrl(User $user, string $secret): string
    {
        $company = config('app.name');
        $holder = $user->email;
        
        return $this->google2fa->getQRCodeUrl(
            $company,
            $holder,
            $secret
        );
    }

    /**
     * Проверить TOTP код
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 2); // 2 временных окна (60 сек каждое)
    }

    /**
     * Сгенерировать код подтверждения для включения/отключения 2FA
     */
    public function generateConfirmationCode(User $user, string $type): TwoFactorConfirmation
    {
        // Удалить старые неиспользованные коды этого типа
        $user->twoFactorConfirmations()
             ->where('type', $type)
             ->where('used', false)
             ->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        return TwoFactorConfirmation::create([
            'user_id' => $user->id,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes(5), // Код действует 5 минут
        ]);
    }

    /**
     * Проверить код подтверждения
     */
    public function verifyConfirmationCode(User $user, string $code, string $type): bool
    {
        $confirmation = $user->twoFactorConfirmations()
                             ->active()
                             ->ofType($type)
                             ->where('code', $code)
                             ->first();

        if (!$confirmation) {
            return false;
        }

        if (!$confirmation->isValid()) {
            return false;
        }

        $confirmation->markAsUsed();
        return true;
    }

    /**
     * Попытка проверки кода с увеличением счетчика попыток
     */
    public function attemptConfirmationCode(User $user, string $code, string $type): array
    {
        $confirmation = $user->twoFactorConfirmations()
                             ->ofType($type)
                             ->where('code', $code)
                             ->where('used', false)
                             ->first();

        if (!$confirmation) {
            return [
                'success' => false,
                'message' => 'Неверный код подтверждения',
                'attempts_left' => 0
            ];
        }

        if ($confirmation->isExpired()) {
            return [
                'success' => false,
                'message' => 'Код подтверждения истек',
                'attempts_left' => 0
            ];
        }

        if ($confirmation->attempts >= 3) {
            return [
                'success' => false,
                'message' => 'Превышено количество попыток',
                'attempts_left' => 0
            ];
        }

        $confirmation->incrementAttempts();
        $attemptsLeft = 3 - $confirmation->attempts;

        if ($confirmation->isValid()) {
            $confirmation->markAsUsed();
            return [
                'success' => true,
                'message' => 'Код подтверждения верен',
                'attempts_left' => $attemptsLeft
            ];
        }

        return [
            'success' => false,
            'message' => 'Неверный код подтверждения',
            'attempts_left' => $attemptsLeft
        ];
    }


    /**
     * Включить 2FA для пользователя
     */
    public function enableTwoFactor(User $user, string $secret, string $code): bool
    {
        if (!$this->verifyCode($secret, $code)) {
            return false;
        }

        $user->enableTwoFactor($secret);
        return true;
    }

    /**
     * Отключить 2FA для пользователя
     */
    public function disableTwoFactor(User $user): void
    {
        $user->disableTwoFactor();
        
        // Удалить все неиспользованные коды подтверждения
        $user->twoFactorConfirmations()->where('used', false)->delete();
    }

    /**
     * Проверить 2FA код при входе
     */
    public function verifyLoginCode(User $user, string $code): bool
    {
        if (!$user->hasTwoFactorEnabled()) {
            return true; // 2FA не включена
        }

        $secret = $user->getTwoFactorSecret();
        
        // Сначала попробовать TOTP код
        if ($this->verifyCode($secret, $code)) {
            return true;
        }

        // Потом попробовать код восстановления
        return $user->useRecoveryCode($code);
    }

    /**
     * Получить статистику использования 2FA
     */
    public function getTwoFactorStats(): array
    {
        $totalUsers = User::count();
        $twoFactorUsers = User::where('two_factor_enabled', true)->count();
        
        return [
            'total_users' => $totalUsers,
            'two_factor_enabled_users' => $twoFactorUsers,
            'percentage' => $totalUsers > 0 ? round(($twoFactorUsers / $totalUsers) * 100, 2) : 0,
            'recent_activations' => User::whereNotNull('two_factor_confirmed_at')
                                      ->where('two_factor_confirmed_at', '>=', now()->subDays(7))
                                      ->count()
        ];
    }
}
