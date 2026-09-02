<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaController;
use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('mfa/challenge', [MfaController::class, 'challenge'])->name('mfa.challenge');
    Route::post('mfa/verify', [MfaController::class, 'verify'])->middleware('throttle:10,1')->name('mfa.verify');
    Route::post('mfa/resend', [MfaController::class, 'resend'])->name('mfa.resend');

    Route::get('forgot-password', [PasswordController::class, 'requestForm'])->name('password.request');
    Route::post('forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset');
    Route::post('reset-password', [PasswordController::class, 'reset'])->name('password.store');

    Route::get('accept-invite/{token}', [InvitationController::class, 'show'])->name('invite.show');
    Route::post('accept-invite/{token}', [InvitationController::class, 'accept'])->name('invite.accept');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('password/change', [PasswordController::class, 'changeForm'])->name('password.change');
    Route::post('password/change', [PasswordController::class, 'change'])->name('password.change.store');

    Route::get('settings/mfa', [MfaController::class, 'setup'])->name('mfa.setup');
    Route::post('settings/mfa/confirm', [MfaController::class, 'confirm'])->name('mfa.confirm');
    Route::delete('settings/mfa', [MfaController::class, 'disable'])->name('mfa.disable');
});
