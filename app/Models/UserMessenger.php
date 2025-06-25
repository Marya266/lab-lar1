<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMessenger extends Model
{
    use HasFactory;

    protected $table = 'users_and_messengers';

    protected $fillable = [
        'user_id',
        'messenger_id',
        'messenger_user_id',
        'status',
        'confirmed_at',
        'notifications_enabled',
        'verification_code',
        'verification_expires_at'
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'verification_expires_at' => 'datetime',
        'notifications_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messenger(): BelongsTo
    {
        return $this->belongsTo(Messenger::class);
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isVerificationCodeValid(): bool
    {
        return $this->verification_code && 
               $this->verification_expires_at && 
               $this->verification_expires_at->isFuture();
    }
}
