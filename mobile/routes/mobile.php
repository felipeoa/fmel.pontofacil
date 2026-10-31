<?php

use App\NativeComponents\Home;
use App\NativeComponents\Login;
use Illuminate\Support\Facades\Route;

Route::native('/', Home::class);

Route::native('/login', Login::class);