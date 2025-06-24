<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'entity_id', 
        'user_id',
        'action',
        'old_values',
        'new_values'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

   
    public function entity()
    {
        $modelClass = 'App\\Models\\' . $this->entity_type;
        
        if (class_exists($modelClass)) {
            return $modelClass::find($this->entity_id);
        }
        
        return null;
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeForSpecificEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
                    ->where('entity_id', $entityId);
    }


    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

 
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
