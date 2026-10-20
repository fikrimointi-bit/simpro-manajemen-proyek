<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CriticalPathService;
use Illuminate\View\View;

/**
 * Halaman analisis jalur kritis (tahap 7B-1) — logika ada di
 * CriticalPathService::hitung, controller hanya memanggil dan me-render.
 */
class CriticalPathController extends Controller
{
    /** Halaman Jalur Kritis: ringkasan, tabel ES/EF/LS/LF/float, daftar jalur. */
    public function index(Project $project, CriticalPathService $jalurKritis): View
    {
        return view('kritis.index', [
            'project' => $project,
            'hasil' => $jalurKritis->hitung($project),
        ]);
    }
}
