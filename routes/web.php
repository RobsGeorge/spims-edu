<?php

use App\Http\Controllers\Admin\ThemeEditorController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FoundationDemoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/api/branding', [BrandingController::class, 'show'])->name('api.branding');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('auth.register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('auth.login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/verify-email', [VerifyEmailController::class, 'show'])->name('auth.verify');
    Route::post('/verify-email', [VerifyEmailController::class, 'store']);
    Route::get('/set-password', [SetPasswordController::class, 'create'])->name('auth.password.create');
    Route::post('/set-password', [SetPasswordController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('auth.password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp']);
    Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])->name('auth.password.reset.form');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('auth.password.reset');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('auth.logout');
Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/me', [MeController::class, 'show'])->name('api.me');

    Route::post('/foundation/demo', [FoundationDemoController::class, 'mutate'])
        ->middleware('permission:foundation.demo')
        ->name('foundation.demo');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users.index');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])
            ->middleware('permission:users.manage')
            ->name('users.suspend');

        Route::get('/theme', [ThemeEditorController::class, 'edit'])
            ->middleware('permission:theme.manage')
            ->name('theme.edit');
        Route::put('/theme/{theme}', [ThemeEditorController::class, 'update'])
            ->middleware('permission:theme.manage')
            ->name('theme.update');
    });
});
