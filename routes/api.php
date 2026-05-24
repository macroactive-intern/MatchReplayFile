<?php

use App\Http\Controllers\Api\ReplayController;
use Illuminate\Support\Facades\Route;

Route::get('/replays/shared/{token}/download', [ReplayController::class, 'sharedDownload']);
Route::get('/replays/shared/{token}', [ReplayController::class, 'shared']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/replays', [ReplayController::class, 'index']);
    Route::post('/replays', [ReplayController::class, 'store']);
    Route::get('/replays/{replay}', [ReplayController::class, 'show']);
    Route::put('/replays/{replay}', [ReplayController::class, 'update']);
    Route::delete('/replays/{replay}', [ReplayController::class, 'destroy']);
    Route::get('/replays/{replay}/download', [ReplayController::class, 'download']);
    Route::post('/replays/{replay}/share', [ReplayController::class, 'share']);
});

Route::middleware('signed')->group(function () {
    Route::get('/replays/{replay}/download-file', [ReplayController::class, 'downloadFile'])
        ->name('api.replays.download.signed');
    Route::get('/replay-shares/{share}/download-file', [ReplayController::class, 'downloadSharedFile'])
        ->name('api.replay-shares.download.signed');
});
