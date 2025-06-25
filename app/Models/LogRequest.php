<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class LogRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'method',
        'url',
        'route_name',
        'controller_class',
        'controller_method',
        'request_body',
        'request_headers',
        'user_id',
        'ip_address',
        'user_agent',
        'response_status',
        'response_body',
        'response_headers',
        'execution_time'
    ];

    protected $casts = [
        'request_headers' => 'array',
        'response_headers' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'execution_time' => 'decimal:3'
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Скоуп для фильтрации по HTTP методу
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('method', strtoupper($method));
    }

    /**
     * Скоуп для фильтрации по пользователю
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Скоуп для фильтрации по IP адресу
     */
    public function scopeByIp($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Скоуп для фильтрации по статусу ответа
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('response_status', $status);
    }

    /**
     * Скоуп для фильтрации по контроллеру
     */
    public function scopeByController($query, string $controller)
    {
        return $query->where('controller_class', 'like', "%{$controller}%");
    }

    /**
     * Скоуп для периода времени
     */
    public function scopeBetweenDates($query, Carbon $from, Carbon $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Скоуп для последних 72 часов
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', now()->subHours(72));
    }

    /**
     * Скоуп для старых записей (старше 72 часов)
     */
    public function scopeExpired($query)
    {
        return $query->where('created_at', '<', now()->subHours(72));
    }

    /**
     * Получить краткое представление запроса
     */
    public function getShortSummaryAttribute(): string
    {
        $controller = $this->controller_class ? class_basename($this->controller_class) : 'Unknown';
        $method = $this->controller_method ?? 'unknown';
        
        return "{$this->method} {$this->url} -> {$controller}@{$method}";
    }

    /**
     * Проверить успешен ли запрос
     */
    public function getIsSuccessfulAttribute(): bool
    {
        return $this->response_status >= 200 && $this->response_status < 300;
    }

    /**
     * Получить размер запроса в байтах
     */
    public function getRequestSizeAttribute(): int
    {
        return strlen($this->request_body ?? '');
    }

    /**
     * Получить размер ответа в байтах
     */
    public function getResponseSizeAttribute(): int
    {
        return strlen($this->response_body ?? '');
    }
}
