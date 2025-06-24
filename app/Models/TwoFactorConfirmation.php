<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TwoFactorConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'type',
        'attempts',
        'expires_at',
        'used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Проверить действительность кода
     */
    public function isValid(): bool
    {
        return !$this->used && 
               $this->expires_at->isFuture() && 
               $this->attempts < 3;
    }

    /**
     * Проверить истек ли код
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Увеличить счетчик попыток
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Отметить как использованный
     */
    public function markAsUsed(): void
    {
        $this->update(['used' => true]);
    }

    /**
     * Скоуп для активных кодов
     */
    public function scopeActive($query)
    {
        return $query->where('used', false)
                    ->where('expires_at', '>', now())
                    ->where('attempts', '<', 3);
    }

    /**
     * Скоуп для конкретного типа
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
