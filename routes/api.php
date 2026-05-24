<?php

use App\Http\Controllers\ReplayDownloadController;
use App\Http\Controllers\ReplayShareController;
use Illuminate\Support\Facades\Route;

Route::get('/replays/shared/{token}/download', [ReplayDownloadController::class, 'forShare']);
Route::get('/replays/shared/{token}', [ReplayShareController::class, 'show']);

Route::middleware('auth')->group(function () {
    Route::get('/replays/{replay}/download', [ReplayDownloadController::class, 'forReplay']);
    Route::post('/replays/{replay}/share', [ReplayShareController::class, 'store']);
});

Route::middleware('signed')->group(function () {
    Route::get('/replays/{replay}/download-file', [ReplayDownloadController::class, 'downloadReplay'])
        ->name('api.replays.download.signed');
    Route::get('/replay-shares/{share}/download-file', [ReplayDownloadController::class, 'downloadShare'])
        ->name('api.replay-shares.download.signed');
});
