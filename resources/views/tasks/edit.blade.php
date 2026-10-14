@extends('layouts.app')

@section('title', 'Edit Tugas')
@section('header-title', 'Edit Tugas')
@section('header-subtitle', $task->name)

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.tasks.index', $project) }}">Tugas</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('projects.tasks.update', [$project, $task]) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('tasks._form', ['task' => $task, 'tugasLain' => $tugasLain, 'parent' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Perbarui
                            </button>
                            <a href="{{ route('projects.tasks.index', $project) }}" class="btn btn-outline-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
