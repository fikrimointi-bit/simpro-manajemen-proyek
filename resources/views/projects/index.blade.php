@extends('layouts.app')

@section('title', 'Daftar Proyek')
@section('header-title', 'Daftar Proyek')
@section('header-subtitle', 'Semua proyek yang sedang Anda kelola')

@section('header-action')
    <a href="{{ route('projects.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tambah Proyek
    </a>
@endsection

@section('content')
    @if ($projects->isEmpty())
        <div class="card">
            <div class="empty-state">
                <i class="bi bi-folder2-open"></i>
                <h5>Belum ada proyek</h5>
                <p>Buat proyek pertama Anda untuk mulai mengatur jadwal, hari libur, dan tugas tim.</p>
                <a href="{{ route('projects.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Proyek
                </a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Organisasi</th>
                            <th>Tanggal Mulai</th>
                            <th>Weekend</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td>
                                    <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-decoration-none">
                                        {{ $project->name }}
                                    </a>
                                    @if ($project->description)
                                        <div class="small text-muted-simpro">{{ \Illuminate\Support\Str::limit($project->description, 60) }}</div>
                                    @endif
                                </td>
                                <td>{{ $project->organization ?: '—' }}</td>
                                <td>{{ $project->start_date->format('d/m/Y') }}</td>
                                <td>
                                    @if ($project->weekend_mode === 'work')
                                        <span class="badge bg-warning text-dark">Weekend tetap kerja</span>
                                    @else
                                        @php
                                            $nama = collect($project->weekend_days ?? [])
                                                ->map(fn ($d) => \App\Support\Tanggal::namaHari((int) $d))
                                                ->implode(', ');
                                        @endphp
                                        <span class="badge bg-secondary">{{ $nama ?: 'Tidak ada' }}</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-secondary"
                                       title="Detail"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('projects.tasks.index', $project) }}" class="btn btn-sm btn-outline-secondary"
                                       title="Tugas"><i class="bi bi-list-task"></i></a>
                                    <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-outline-primary"
                                       title="Edit"><i class="bi bi-pencil"></i></a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                            data-bs-toggle="modal" data-bs-target="#modalKonfirmasi"
                                            data-url="{{ route('projects.destroy', $project) }}"
                                            data-pesan="Hapus proyek &laquo;{{ $project->name }}&raquo; beserta seluruh tugas, sumber daya, dan hari liburnya?">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
