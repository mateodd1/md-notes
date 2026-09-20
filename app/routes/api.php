<?php

use App\Http\Controllers\ApiNoteController;
use Illuminate\Support\Facades\Route;

$publicHost = parse_url(config('md-notes.canonical_url'), PHP_URL_HOST);
$workspaceHost = parse_url(config('md-notes.workspace_url'), PHP_URL_HOST);
$separateWorkspace = $workspaceHost && $workspaceHost !== $publicHost;

Route::domain($separateWorkspace ? $publicHost : null)->middleware(['api.token', 'throttle:60,1,api.notes.upload:'])->group(function (): void {
    Route::put('/notes/{path}', [ApiNoteController::class, 'upload'])
        ->where('path', '.*')
        ->name('api.notes.upload');
});

if ($separateWorkspace) {
    // Keep compatibility aliases without redirecting requests or stripping Bearer tokens.
    foreach (config('md-notes.legacy_hosts', []) as $index => $legacyHost) {
        if (in_array($legacyHost, [$publicHost, $workspaceHost], true)) {
            continue;
        }

        Route::domain($legacyHost)->middleware(['api.token', 'throttle:60,1,api.notes.upload:'])
            ->put('/notes/{path}', [ApiNoteController::class, 'upload'])
            ->where('path', '.*')
            ->name('api.notes.legacy-upload.'.$index);
    }
}
