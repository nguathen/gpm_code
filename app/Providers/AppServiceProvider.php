<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Profile;
use App\Observers\ProfileFileObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Profile::observe(ProfileFileObserver::class);
    }
}
