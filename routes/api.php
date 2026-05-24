<?php

use App\Http\Controllers\ReplayShareController;
use Illuminate\Support\Facades\Route;

Route::get('/replays/shared/{token}', [ReplayShareController::class, 'show']);

Route::middleware('auth')->post('/replays/{replay}/share', [ReplayShareController::class, 'store']);
