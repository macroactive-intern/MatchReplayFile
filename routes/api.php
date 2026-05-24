<?php

use App\Http\Controllers\ReplayShareController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->post('/replays/{replay}/share', [ReplayShareController::class, 'store']);
