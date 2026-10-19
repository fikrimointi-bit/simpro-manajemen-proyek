@extends('layouts.app')

@section('title', 'Diagram PERT: ' . $project->name)
@section('header-title', 'Diagram PERT')
@section('header-subtitle', $project->name)

@section('header-action')
    <a href="{{ route('projects.tasks.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-list-task me-1"></i> Kelola Tugas
    </a>
    <a href="{{ route('projects.gantt.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-bar-chart-steps me-1"></i> Gantt Chart
    </a>
    <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i> Edit Proyek
    </a>
@endsection

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Diagram PERT</li>
        </ol>
    </nav>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-diagram-3 me-1"></i> Diagram PERT (Activity-on-Node)</strong>
            <a href="{{ route('projects.pert.data', $project) }}" class="btn btn-sm btn-outline-secondary"
               target="_blank" rel="noopener">
                <i class="bi bi-code-slash me-1"></i> Lihat JSON
            </a>
        </div>

        {{-- area diagram: dirender pada tahap berikutnya --}}
        <div class="empty-state">
            <i class="bi bi-diagram-3"></i>
            <h5>Diagram akan tampil di sini</h5>
            <p class="mb-0">Node tugas, panah dependensi, dan layer kolom akan digambar pada tahap berikutnya.</p>
        </div>
    </div>
@endsection
