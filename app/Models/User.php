<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, Auditable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

     public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'users_roles', 'user_id', 'role_id')
                    ->withTimestamps();
    }

    /**
     * Проверить есть ли у пользователя роль
     */
    public function hasRole(string $roleCode): bool
    {
        return $this->roles()->where('code', $roleCode)->exists();
    }

    /**
     * Проверить есть ли у пользователя разрешение
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->roles()
                    ->whereHas('permissions', function ($query) use ($permissionCode) {
                        $query->where('code', $permissionCode);
                    })
                    ->exists();
    }

    /**
     * Назначить роль пользователю
     */
    public function assignRole(Role $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->id]);
    }

    /**
     * Убрать роль у пользователя
     */
    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
    }

    /**
     * Получить все разрешения пользователя через роли
     */
    public function getAllPermissions()
    {
        return $this->roles()
                    ->with('permissions')
                    ->get()
                    ->pluck('permissions')
                    ->flatten()
                    ->unique('id');
    }

    /**
     * Связь с подтверждениями 2FA
     */
    public function twoFactorConfirmations(): HasMany
    {
        return $this->hasMany(TwoFactorConfirmation::class);
    }

    /**
     * Проверить включена ли 2FA
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && 
               $this->two_factor_secret && 
               $this->two_factor_confirmed_at;
    }

    /**
     * Включить 2FA
     */
    public function enableTwoFactor(string $secret): void
    {
        $this->update([
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
        ]);
    }

    /**
     * Отключить 2FA
     */
    public function disableTwoFactor(): void
    {
        $this->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);
    }

    /**
     * Получить расшифрованный секрет 2FA
     */
    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret ? decrypt($this->two_factor_secret) : null;
    }

    /**
     * Сгенерировать коды восстановления
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
        }
        return $codes;
    }

    /**
     * Использовать код восстановления
     */
    public function useRecoveryCode(string $code): bool
    {
        $recoveryCodes = $this->two_factor_recovery_codes;
        
        if (!$recoveryCodes || !in_array($code, $recoveryCodes)) {
            return false;
        }

        // Удалить использованный код
        $this->update([
            'two_factor_recovery_codes' => array_values(array_diff($recoveryCodes, [$code]))
        ]);

        return true;
    }

    public function messengers(): BelongsToMany
{
    return $this->belongsToMany(Messenger::class, 'users_and_messengers')
                ->withPivot([
                    'messenger_user_id',
                    'status', 
                    'confirmed_at',
                    'notifications_enabled',
                    'verification_code',
                    'verification_expires_at'
                ])
                ->withTimestamps();
}

public function userMessengers(): HasMany
{
    return $this->hasMany(UserMessenger::class);
}

public function confirmedMessengers(): BelongsToMany
{
    return $this->messengers()->wherePivot('status', 'confirmed');
}

public function enabledMessengers(): BelongsToMany
{
    return $this->confirmedMessengers()->wherePivot('notifications_enabled', true);
}



}
