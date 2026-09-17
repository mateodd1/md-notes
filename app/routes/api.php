<?php

use App\Http\Controllers\ApiNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.token', 'throttle:60,1'])->group(function (): void {
    Route::put('/notes/{path}', [ApiNoteController::class, 'upload'])
        ->where('path', '.*\\.md')
        ->name('api.notes.upload');
});
