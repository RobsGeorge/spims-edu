<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Middleware\Api\SetApiLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| /api/v1 — mobile clients (student, instructor)
|--------------------------------------------------------------------------
|
| Bearer-token auth via Sanctum. Controllers are thin: validate, delegate to
| the same App\Services\* used by the web app, return a Resource or a plain
| `data`-wrapped array. See docs/api/openapi.yaml for the contract and
| docs/academic-roadmap/mobile-api-spec.md for the full design.
|
*/
Route::prefix('v1')->name('api.v1.')->middleware(SetApiLocale::class)->group(function () {
    Route::get('/branding', [BrandingController::class, 'show'])->name('branding');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [MeController::class, 'show'])->name('me');
    });
});
