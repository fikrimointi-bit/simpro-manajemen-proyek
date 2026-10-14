@extends('layouts.app')

@section('title', 'Beranda')
@section('header-title', 'Beranda')
@section('header-subtitle', 'Ringkasan proyek dan aktivitas terbaru Anda')

@section('content')
    {{-- Kartu statistik --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-ikon"><i class="bi bi-folder2"></i></div>
                    <div>
                        <div class="stat-nilai">{{ $jumlahProyek }}</div>
                        <div class="stat-label">Proyek</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-ikon"><i class="bi bi-list-task"></i></div>
                    <div>
                        <div class="stat-nilai">{{ $jumlahTugas }}</div>
                        <div class="stat-label">Tugas</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-ikon"><i class="bi bi-calendar2-heart"></i></div>
                    <div>
                        <div class="stat-nilai">{{ $jumlahLibur }}</div>
                        <div class="stat-label">Hari Libur</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-ikon"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="stat-nilai">{{ $jumlahAnggota }}</div>
                        <div class="stat-label">Anggota</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Proyek terbaru --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Proyek Terbaru</h2>
        @if ($jumlahProyek > 0)
            <a href="{{ route('projects.index') }}" class="btn btn-sm btn-outline-secondary">
                Lihat semua <i class="bi bi-arrow-right"></i>
            </a>
        @endif
    </div>

    @if ($proyekTerbaru->isEmpty())
        <div class="card">
            <div class="empty-state">
                <i class="bi bi-folder2-open"></i>
                <h5>Belum ada proyek</h5>
                <p>Mulai dengan membuat proyek pertama Anda — atur hari libur dan akhirnya susun tugasnya di chart Gantt.</p>
                <a href="{{ route('projects.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Proyek
                </a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($proyekTerbaru as $proyek)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h3 class="h6 mb-0">
                                    <a href="{{ route('projects.show', $proyek) }}" class="text-decoration-none text-reset">
                                        {{ $proyek->name }}
                                    </a>
                                </h3>
                                @if ($proyek->weekend_mode === 'work')
                                    <span class="badge bg-warning text-dark">Weekend kerja</span>
                                @else
                                    <span class="badge bg-secondary">Weekend libur</span>
                                @endif
                            </div>

                            <p class="text-muted-simpro small mb-2">
                                {{ $proyek->organization ?: 'Tanpa organisasi' }}
                            </p>

                            <div class="small text-muted-simpro mb-3">
                                <div><i class="bi bi-calendar3 me-1"></i>
                                    Mulai {{ \App\Support\Tanggal::pendek($proyek->start_date) }}</div>
                                <div><i class="bi bi-list-task me-1"></i>
                                    {{ $proyek->tasks_count }} tugas</div>
                            </div>

                            <div class="mt-auto d-flex gap-2">
                                <a href="{{ route('projects.show', $proyek) }}" class="btn btn-sm btn-primary flex-grow-1">
                                    Buka
                                </a>
                                <a href="{{ route('projects.tasks.index', $proyek) }}" class="btn btn-sm btn-outline-secondary">
                                    Tugas
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
