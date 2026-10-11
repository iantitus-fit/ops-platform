<?php

namespace App\Providers;

use App\Icm\ActiveConfig;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * ActiveConfig is bound as scoped: the container hands out one instance per request or
     * queued job and builds a fresh one for the next. The queue worker forgets scoped
     * instances after every job, so a long-running worker picks up a sync that ran in
     * another process. A plain singleton would serve the old version until the worker restarts.
     */
    public function register(): void
    {
        $this->app->scoped(ActiveConfig::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
