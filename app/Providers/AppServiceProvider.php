<?php

namespace App\Providers;

use App\Models\Replay;
use App\Observers\ReplayObserver;
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
        Replay::observe(ReplayObserver::class);
    }
}
