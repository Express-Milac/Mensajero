<?php

use App\Http\Controllers\Auth\SessionController;
use App\Services\InfrastructureHealth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/health', function (InfrastructureHealth $health) {
        $checks = $health->check();

        return response()->json(['checks' => $checks], in_array('failed', $checks, true) ? 503 : 200);
    })->middleware(['can:administer', 'throttle:10,1'])->name('health');
});
