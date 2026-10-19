<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GanttController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\PertController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResourceController;
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

// sumber daya (anggota tim) per proyek — tahap 4A; show = detail anggota
Route::resource('projects.resources', ResourceController::class)
    ->parameters(['projects' => 'project', 'resources' => 'resource']);

// cuti pribadi per anggota dari halaman detail anggota
Route::post('/projects/{project}/resources/{resource}/days-off', [ResourceController::class, 'storeDayOff'])
    ->name('resource-days-off.store');
Route::delete('/projects/{project}/resources/{resource}/days-off/{dayOff}', [ResourceController::class, 'destroyDayOff'])
    ->name('resource-days-off.destroy');

// Chart Gantt dasar (tahap 5A): halaman + endpoint JSON-nya
Route::get('/projects/{project}/gantt', [GanttController::class, 'index'])
    ->name('projects.gantt.index');
Route::get('/projects/{project}/gantt/data', [GanttController::class, 'data'])
    ->name('projects.gantt.data');

// Diagram PERT gaya Activity-on-Node (tahap 6A): halaman + endpoint JSON-nya
Route::get('/projects/{project}/pert', [PertController::class, 'index'])
    ->name('projects.pert.index');
Route::get('/projects/{project}/pert/data', [PertController::class, 'data'])
    ->name('projects.pert.data');
