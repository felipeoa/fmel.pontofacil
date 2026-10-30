<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PunchController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Controllers\Api\WorkSummaryController;
use App\Http\Controllers\Api\BankController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:5,1');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () 
{
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    
    //Registro de Ponto
    Route::get('/punches',                  [PunchController::class, 'index']);
    Route::post('/punches',                 [PunchController::class, 'store']);
    Route::put('/punches/{id}',             [PunchController::class, 'update']);
    Route::delete('/punches/{id}',          [PunchController::class, 'destroy']);
    Route::post('/punches/{id}/restore',    [PunchController::class, 'restore']);
    Route::get('/punches/{id}/audit',       [PunchController::class, 'audit']);

    //Jornada
    Route::get('/work-schedules',       [WorkScheduleController::class, 'index']);
    Route::post('/work-schedules',      [WorkScheduleController::class, 'store']);
    Route::get('/work-schedules/{id}',  [WorkScheduleController::class, 'show']);
    Route::put('/work-schedules/{id}',  [WorkScheduleController::class, 'update']);    

    // Apuração da jornada
    Route::get('/work-summary/{date}',              [WorkSummaryController::class, 'show']);
    Route::post('/work-summary/{date}/calculate',   [WorkSummaryController::class, 'calculate']);

    // Banco de horas
    Route::get('/bank-balance',         [BankController::class, 'balance']);
    Route::get('/bank-transactions',    [BankController::class, 'transactions']);
});