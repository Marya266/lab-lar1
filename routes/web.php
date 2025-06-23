<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InfoController;



Route::get('/', function () {
    return view('welcome');
});

Route::get('/phpinfo', [InfoController::class, 'phpInfo']);

Route::get('/useragent', [InfoController::class, 'clientData']);

Route::get('/database', [InfoController::class, 'databaseInfo']);
