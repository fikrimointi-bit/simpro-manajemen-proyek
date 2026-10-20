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

        // submenu sidebar per proyek: "Sumber Daya" (tahap 4A), "Gantt Chart" (5A),
        // "Diagram PERT" (6A) & "Jalur Kritis" (7B)
        View::composer('layouts.app', function ($view): void {
            $proyekMenu = Project::orderBy('name')->get(['id', 'name']);

            $view->with([
                'menuSumberDaya' => $proyekMenu,
                'menuGantt' => $proyekMenu,
                'menuPert' => $proyekMenu,
                'menuKritis' => $proyekMenu,
            ]);
        });
    }
}
