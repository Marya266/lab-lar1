<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Messenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'environment',
        'token_env_variable',
        'api_endpoint',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_and_messengers')
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

    public function getTokenFromEnv(): ?string
    {
        return env($this->token_env_variable);
    }

    public function scopeForEnvironment($query, $environment = null)
    {
        $env = $environment ?? app()->environment();
        return $query->where('environment', $env)->where('is_active', true);
    }
}
