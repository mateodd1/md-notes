<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\NoteMediaController;
use App\Http\Controllers\NotePdfController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NoteVersionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\SitemapController;
use App\Services\ShareTokens;
use Illuminate\Support\Facades\Route;

$publicHost = parse_url(config('md-notes.canonical_url'), PHP_URL_HOST);
$workspaceHost = parse_url(config('md-notes.workspace_url'), PHP_URL_HOST);
$separateWorkspace = $workspaceHost && $workspaceHost !== $publicHost;

Route::domain($separateWorkspace ? $publicHost : null)->group(function () use ($separateWorkspace): void {
    Route::view('/', 'welcome')->name('home');
    Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
    Route::get('/documentation.md', [DocumentationController::class, 'show'])->name('documentation');
    if ($separateWorkspace) {
        Route::get('/share/{token}/media/{filename}', [ShareController::class, 'redirectMediaToWorkspace'])
            ->where('token', ShareTokens::ROUTE_PATTERN)
            ->where('filename', '[a-z0-9]{24}\.[a-z0-9]{1,10}')
            ->name('shares.media.short');
        Route::get('/share/{token}', [ShareController::class, 'redirectShareToWorkspace'])
            ->where('token', ShareTokens::ROUTE_PATTERN)
            ->name('shares.short');
    }
});

Route::domain($separateWorkspace ? $workspaceHost : null)->group(function () use ($separateWorkspace): void {

    Route::get('/account-export/{token}', [ProfileController::class, 'downloadAccountExport'])
        ->where('token', '[a-f0-9]{64}')
        ->middleware('throttle:10,1,account-exports.download:')
        ->name('account-exports.download');

    if (! $separateWorkspace) {
        Route::get('/media/{filename}', [NoteMediaController::class, 'show'])
            ->where('filename', '[a-z0-9]{24}\.[a-z0-9]{1,10}')
            ->middleware('auth')
            ->name('media.legacy');
    }

    Route::get('/share/{token}/media/{filename}', [ShareController::class, 'media'])
        ->where('token', ShareTokens::ROUTE_PATTERN)
        ->where('filename', '[a-z0-9]{24}\.[a-z0-9]{1,10}')
        ->middleware('throttle:shares-media')
        ->name('shares.media');
    Route::post('/share/{token}/copy', [ShareController::class, 'copyToSpace'])
        ->where('token', ShareTokens::ROUTE_PATTERN)
        ->middleware(['auth', 'throttle:shares-copy'])
        ->name('shares.copy');
    Route::get('/share/{token}', [ShareController::class, 'show'])
        ->where('token', ShareTokens::ROUTE_PATTERN)
        ->middleware('throttle:shares-read')
        ->name('shares.show');

    Route::get('/session-status', [AuthController::class, 'sessionStatus'])
        ->middleware('throttle:60,1,session-status:')
        ->name('session.status');

    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [AuthController::class, 'create'])->name('login');
        Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::get('/signup', [AuthController::class, 'registerForm'])->name('register');
        Route::post('/signup', [AuthController::class, 'register'])->middleware('throttle:10,1,register.store:')->name('register.store');
        Route::get('/forgot-password', [AuthController::class, 'forgotPasswordForm'])->name('password.request');
        Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:password-email')->name('password.email');
        Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');
    });

    Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware('auth')->prefix($separateWorkspace ? '' : 'app')->group(function (): void {
        Route::get('/', [NotesController::class, 'index'])->name('notes.index');
        Route::get('/search', [NotesController::class, 'search'])->middleware('throttle:60,1,notes.search:')->name('notes.search');
        Route::get('/quota', [NotesController::class, 'quota'])->middleware('throttle:15,1,quota.show:')->name('quota.show');
        Route::post('/folders', [NotesController::class, 'storeFolder'])->name('folders.store');
        Route::post('/notes', [NotesController::class, 'storeNote'])->name('notes.store');
        Route::post('/shares', [ShareController::class, 'store'])->name('shares.store');
        Route::get('/shared', [ShareController::class, 'index'])->name('shares.index');
        Route::patch('/shared/{share}', [ShareController::class, 'update'])->name('shares.update');
        Route::delete('/shared/{share}', [ShareController::class, 'destroy'])->name('shares.destroy');
        Route::get('/settings', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/settings/name', [ProfileController::class, 'updateName'])->name('profile.name');
        Route::post('/settings/api-tokens', [ProfileController::class, 'storeApiToken'])->name('profile.api-tokens.store');
        Route::delete('/settings/api-tokens/{token}', [ProfileController::class, 'destroyApiToken'])->name('profile.api-tokens.destroy');
        Route::post('/settings/account-export', [ProfileController::class, 'requestAccountExport'])->middleware('throttle:3,1,profile.account-exports.store:')->name('profile.account-exports.store');
        Route::post('/settings/password/code', [ProfileController::class, 'sendPasswordCode'])->middleware('throttle:3,15,profile.password.code:')->name('profile.password.code');
        Route::patch('/settings/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:10,15,profile.password:')->name('profile.password');
        Route::delete('/settings', [ProfileController::class, 'destroy'])->name('profile.destroy');
        Route::get('/history/{path}', [NoteVersionController::class, 'index'])->where('path', '.*\.md')->name('versions.index');
        Route::get('/versions/{version}', [NoteVersionController::class, 'show'])->name('versions.show');
        Route::patch('/versions/{version}/restore', [NoteVersionController::class, 'restore'])->name('versions.restore');
        Route::get('/versions/{version}/download', [NoteVersionController::class, 'download'])->name('versions.download');
        Route::post('/media', [NoteMediaController::class, 'store'])->name('media.store');
        Route::get('/media/{filename}/preview', [NoteMediaController::class, 'previewPdf'])
            ->where('filename', '[a-z0-9]{24}\.[a-z0-9]{1,10}')
            ->name('media.preview');
        Route::get('/media/{filename}', [NoteMediaController::class, 'show'])
            ->where('filename', '[a-z0-9]{24}\.[a-z0-9]{1,10}')
            ->name('media.show');
        Route::patch('/organize', [NotesController::class, 'move'])->name('notes.move');
        Route::patch('/reorder', [NotesController::class, 'reorder'])->name('notes.reorder');
        Route::patch('/reorder-folders', [NotesController::class, 'reorderFolder'])->name('folders.reorder');
        Route::patch('/item/rename', [NotesController::class, 'rename'])->name('items.rename');
        Route::patch('/item/pin', [NotesController::class, 'pin'])->name('items.pin');
        Route::delete('/item', [NotesController::class, 'destroyItem'])->name('items.destroy');
        Route::get('/trash', [NotesController::class, 'trashIndex'])->name('trash.index');
        Route::get('/trash/{id}/view', [NotesController::class, 'showTrash'])->where('id', '\\d{14}-[a-f0-9]{16}')->name('trash.show');
        Route::post('/trash/{id}/restore', [NotesController::class, 'restoreTrash'])->where('id', '\\d{14}-[a-f0-9]{16}')->name('trash.restore');
        Route::delete('/trash/{id}', [NotesController::class, 'destroyTrash'])->where('id', '\\d{14}-[a-f0-9]{16}')->name('trash.destroy');
        Route::get('/properties/{path}', [NotesController::class, 'properties'])->where('path', '.*\.md')->name('notes.properties');
        Route::get('/download/{path}', [NotesController::class, 'download'])->where('path', '.*\.md')->name('notes.download');
        Route::get('/{path}/pdf', NotePdfController::class)->where('path', '.*\.md')->middleware('throttle:6,1,notes.pdf:')->name('notes.pdf');
        Route::get('/{path}', [NotesController::class, 'show'])->where('path', '.*\.md')->name('notes.show');
        Route::put('/{path}', [NotesController::class, 'update'])->where('path', '.*\.md')->name('notes.update');
        Route::delete('/{path}', [NotesController::class, 'destroy'])->where('path', '.*\.md')->name('notes.destroy');
    });
});
