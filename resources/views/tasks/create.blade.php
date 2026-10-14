@extends('layouts.app')

@section('title', $parent ? 'Tambah Sub-tugas' : 'Tambah Tugas')
@section('header-title', $parent ? 'Tambah Sub-tugas' : 'Tambah Tugas')
@section('header-subtitle', $parent ? 'Sub-tugas dari: ' . $parent->name : 'Tugas induk/mandiri di proyek ' . $project->name)

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.tasks.index', $project) }}">Tugas</a></li>
            <li class="breadcrumb-item active" aria-current="page">Tambah</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('projects.tasks.store', $project) }}" method="POST">
                        @csrf
                        @include('tasks._form', ['task' => null, 'tugasLain' => $tugasLain, 'parent' => $parent])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Simpan
                            </button>
                            <a href="{{ route('projects.tasks.index', $project) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
