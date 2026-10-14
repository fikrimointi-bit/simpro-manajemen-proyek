<?php

namespace App\Providers;

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
        // antarmuka berbahasa Indonesia, jadi locale dipaksa 'id'
        // (menang juga kalau di .env tertulis APP_LOCALE=en)
        $this->app->setLocale('id');
    }
}
