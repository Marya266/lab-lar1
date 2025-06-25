<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\GitWebhookController;
use App\Http\Controllers\DeploymentLogController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-2fa', [AuthController::class, 'verify2FA']);



Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);

     Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/git/status', [GitWebhookController::class, 'getStatus']);
        Route::post('/git/deploy', [GitWebhookController::class, 'handleWebhook']); // Ручной деплой
    });



    Route::prefix('2fa')->group(function () {
        Route::get('/status', [TwoFactorController::class, 'status']);
        Route::post('/setup', [TwoFactorController::class, 'setup']);
        Route::post('/enable', [TwoFactorController::class, 'enable']);
        Route::post('/disable', [TwoFactorController::class, 'disable']);
        Route::post('/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
        
        Route::middleware('permission:roles.manage')->get('/statistics', [TwoFactorController::class, 'statistics']);
    });

    Route::get('/users', [UserController::class, 'index']);
    Route::apiResource('roles', RoleController::class);    
    Route::middleware('permission:users.edit')->group(function () {
        Route::post('/users/{user}/roles/{role}', [UserController::class, 'assignRole']);
        Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole']);
    });

    Route::apiResource('permissions', PermissionController::class);

});

Route::get('/login', function () {
    return response()->json([
        'message' => 'Это API приложение. Используйте POST /api/login для авторизации.'
    ], 401);
})->name('login');

Route::post('/git/webhook', [GitWebhookController::class, 'handleWebhook']);


Route::middleware('auth:sanctum')->get('/2fa/current-code', function (Request $request) {
    $user = $request->user();
    $secret = cache()->get("2fa_setup_{$user->id}");
    
    if (!$secret) {
        return response()->json(['error' => 'Секрет не найден. Начните настройку 2FA заново.']);
    }
    
    $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
    $currentCode = $google2fa->getCurrentOtp($secret);
    
    return response()->json([
        'secret' => $secret,
        'current_code' => $currentCode,
        'message' => 'Используйте этот код для включения 2FA',
        'expires_in_seconds' => 30 - (time() % 30)
    ]);
});

Route::middleware('permission:roles.manage')->group(function () {
    Route::prefix('deployment')->group(function () {
        Route::get('/logs', [DeploymentLogController::class, 'getLogs']);
        Route::delete('/logs', [DeploymentLogController::class, 'clearLogs']);
    });
});
