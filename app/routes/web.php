<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NoteVersionController;
use App\Http\Controllers\NoteMediaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShareController;
use Illuminate\Support\Facades\Route;

Route::get('/share/{token}/media/{filename}', [ShareController::class, 'media'])
    ->where('token', '[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5}')
    ->where('filename', '[a-z0-9]{24}\.(?:jpg|png|gif|webp)')
    ->middleware('throttle:60,1')
    ->name('shares.media');
Route::get('/share/{token}', [ShareController::class, 'show'])->where('token', '[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{5}')->middleware('throttle:60,1')->name('shares.show');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/singup', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/singup', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [NotesController::class, 'index'])->name('notes.index');
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
    Route::post('/settings/password/code', [ProfileController::class, 'sendPasswordCode'])->middleware('throttle:3,15')->name('profile.password.code');
    Route::patch('/settings/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/settings', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/history/{path}', [NoteVersionController::class, 'index'])->where('path', '.*\.md')->name('versions.index');
    Route::get('/versions/{version}', [NoteVersionController::class, 'show'])->name('versions.show');
    Route::patch('/versions/{version}/restore', [NoteVersionController::class, 'restore'])->name('versions.restore');
    Route::get('/versions/{version}/download', [NoteVersionController::class, 'download'])->name('versions.download');
    Route::post('/media', [NoteMediaController::class, 'store'])->name('media.store');
    Route::get('/media/{filename}', [NoteMediaController::class, 'show'])->name('media.show');
    Route::patch('/organize', [NotesController::class, 'move'])->name('notes.move');
    Route::patch('/reorder', [NotesController::class, 'reorder'])->name('notes.reorder');
    Route::patch('/item/rename', [NotesController::class, 'rename'])->name('items.rename');
    Route::delete('/item', [NotesController::class, 'destroyItem'])->name('items.destroy');
    Route::get('/download/{path}', [NotesController::class, 'download'])->where('path', '.*\.md')->name('notes.download');
    Route::get('/{path}', [NotesController::class, 'show'])->where('path', '.*\.md')->name('notes.show');
    Route::put('/{path}', [NotesController::class, 'update'])->where('path', '.*\.md')->name('notes.update');
    Route::delete('/{path}', [NotesController::class, 'destroy'])->where('path', '.*\.md')->name('notes.destroy');
});
