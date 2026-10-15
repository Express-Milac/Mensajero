<?php

use App\Http\Controllers\Auth\SessionController;
use App\Services\InfrastructureHealth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/login', function () {
    if (config('mensajero.auth.mode') !== 'local') {
        return redirect()->route('dashboard');
    }

    return view('auth.login');
})->name('login');

Route::post('/login', [SessionController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::middleware('mensajero.access')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/health', function (InfrastructureHealth $health) {
        $checks = $health->check();

        return response()->json(
            ['checks' => $checks],
            in_array('failed', $checks, true) ? 503 : 200,
        );
    })->middleware(['mensajero.access:admin', 'throttle:10,1'])->name('health');
});
