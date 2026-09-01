<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', HealthController::class)
    ->middleware(['health.token', 'throttle:30,1'])
    ->name('api.health');
