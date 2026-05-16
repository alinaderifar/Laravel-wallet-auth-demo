<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WalletAuthController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'sandbox')->name('sandbox');

Route::post('/auth/nonce', [WalletAuthController::class, 'nonce'])->name('auth.nonce');
Route::post('/auth/verify', [WalletAuthController::class, 'verify'])->name('auth.verify');
Route::post('/auth/logout', [WalletAuthController::class, 'logout'])->middleware('auth')->name('auth.logout');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');
