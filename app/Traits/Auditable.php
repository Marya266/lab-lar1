<?php

namespace App\Traits;

use App\Services\ChangeLogService;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            if (Auth::check()) {
                app(ChangeLogService::class)->logCreated($model, Auth::id());
            }
        });

        static::updated(function ($model) {
            if (Auth::check() && $model->wasChanged()) {
                $oldValues = $model->getOriginal();
                $changedAttributes = array_intersect_key($oldValues, $model->getDirty());
                
                app(ChangeLogService::class)->logUpdated($model, $changedAttributes, Auth::id());
            }
        });

        static::deleted(function ($model) {
            if (Auth::check()) {
                app(ChangeLogService::class)->logDeleted($model, Auth::id());
            }
        });
    }
}
