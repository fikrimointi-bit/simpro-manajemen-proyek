<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

// beranda: ringkasan proyek & proyek terbaru
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// CRUD proyek
Route::resource('projects', ProjectController::class)->parameters(['projects' => 'project']);

// pengaturan weekend dari halaman detail
Route::patch('/projects/{project}/weekend', [ProjectController::class, 'updateWeekend'])
    ->name('projects.weekend.update');

// kelola hari libur dari halaman detail
Route::post('/projects/{project}/holidays', [HolidayController::class, 'store'])
    ->name('holidays.store');
Route::delete('/projects/{project}/holidays/{holiday}', [HolidayController::class, 'destroy'])
    ->name('holidays.destroy');

// CRUD tugas bersarang di dalam proyek (tanpa halaman show terpisah)
Route::resource('projects.tasks', TaskController::class)
    ->parameters(['projects' => 'project', 'tasks' => 'task'])
    ->except('show');
