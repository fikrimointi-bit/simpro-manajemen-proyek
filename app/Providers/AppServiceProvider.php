<?php

namespace App\Providers;

use App\Models\Project;
use Illuminate\Support\Facades\View;
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

        // menu sidebar "Sumber Daya": satu tautan per proyek (tahap 4A)
        View::composer('layouts.app', function ($view): void {
            $view->with('menuSumberDaya', Project::orderBy('name')->get(['id', 'name']));
        });
    }
}
