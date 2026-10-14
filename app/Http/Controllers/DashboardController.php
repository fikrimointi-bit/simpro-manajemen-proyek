<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use Illuminate\View\View;

/** Beranda: ringkasan singkat proyek dan proyek terbaru. */
class DashboardController extends Controller
{
    /** Hanya baca data untuk statistik — tanpa logika bisnis apa pun. */
    public function index(): View
    {
        return view('dashboard', [
            'jumlahProyek' => Project::count(),
            'jumlahTugas' => Task::count(),
            'jumlahLibur' => Holiday::count(),
            'jumlahAnggota' => Resource::count(),
            // 6 proyek terbaru, sekalian jumlah tugasnya (hemat query)
            'proyekTerbaru' => Project::latest('id')->withCount('tasks')->take(6)->get(),
        ]);
    }
}
