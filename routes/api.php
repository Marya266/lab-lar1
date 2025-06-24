<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [UserController::class, 'show']);
    Route::put('/user', [UserController::class, 'update']);
    Route::get('/users', [UserController::class, 'index']);
    Route::apiResource('roles', RoleController::class);
    
    Route::apiResource('permissions', PermissionController::class);

});

Route::get('/login', function () {
    return response()->json([
        'message' => 'Это API приложение. Используйте POST /api/login для авторизации.'
    ], 401);
})->name('login');

