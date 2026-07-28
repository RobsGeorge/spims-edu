<?php

use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\FoundationDemoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/api/branding', [BrandingController::class, 'show'])->name('api.branding');

Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth'])->group(function () {
    Route::post('/foundation/demo', [FoundationDemoController::class, 'mutate'])
        ->middleware('permission:foundation.demo')
        ->name('foundation.demo');
});
