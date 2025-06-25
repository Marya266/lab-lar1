<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectDeploymentLogs
{
    public function handle(Request $request, Closure $next): Response
    {
        // Проверить что пользователь авторизован и имеет права администратора
        if (!$request->user() || !$request->user()->hasPermission('roles.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав для доступа к логам деплоя'
            ], 403);
        }

        return $next($request);
    }
}
