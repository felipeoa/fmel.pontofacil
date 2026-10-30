<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PunchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/punches', [PunchController::class, 'index']);
    Route::post('/punches', [PunchController::class, 'store']);
});