<?php

namespace App\Providers;

use App\Models\Replay;
use App\Policies\ReplayPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::policy(Replay::class, ReplayPolicy::class);
    }
}
