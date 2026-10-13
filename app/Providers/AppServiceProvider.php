<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('administer', fn (User $user) => $user->role === Role::Administrator);
        Gate::define('review-events', fn (User $user) => in_array($user->role, [Role::Administrator, Role::Supervisor], true));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.hash('sha256', mb_strtolower((string) $request->input('email')))),
        ]);
    }
}
