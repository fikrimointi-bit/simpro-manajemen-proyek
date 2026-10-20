@extends('layouts.app')

@section('title', 'Jalur Kritis: ' . $project->name)
@section('header-title', 'Jalur Kritis')
@section('header-subtitle', $project->name)

@section('header-action')
    <a href="{{ route('projects.tasks.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-list-task me-1"></i> Kelola Tugas
    </a>
    <a href="{{ route('projects.gantt.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-bar-chart-steps me-1"></i> Gantt Chart
    </a>
    <a href="{{ route('projects.pert.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-diagram-3 me-1"></i> Diagram PERT
    </a>
@endsection

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Jalur Kritis</li>
        </ol>
    </nav>

    @if (empty($hasil['tugas']))
        {{-- proyek belum punya tugas leaf: tampilan kosong yang ramah --}}
        <div class="card">
            <div class="empty-state">
                <i class="bi bi-signpost-split"></i>
                <h5>Belum ada tugas</h5>
                <p>Tambahkan tugas dulu agar jalur kritis proyek bisa dihitung.</p>
                <a href="{{ route('projects.tasks.create', $project) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Tambah Tugas
                </a>
            </div>
        </div>
    @else
        {{-- kartu ringkasan: durasi total + jumlah tugas kritis --}}
        <div class="row mb-3">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="stat-ikon"><i class="bi bi-hourglass-split"></i></span>
                        <div>
                            <div class="stat-nilai">{{ $hasil['durasi_total'] }}</div>
                            <div class="stat-label">hari kerja — durasi total proyek (jalur kritis)</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="stat-ikon"><i class="bi bi-exclamation-octagon-fill"></i></span>
                        <div>
                            <div class="stat-nilai text-danger">{{ count($hasil['tugas_kritis']) }}</div>
                            <div class="stat-label">tugas kritis (float 0) — keterlambatannya menunda proyek</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- tabel tugas: ES/EF/LS/LF/float; baris & badge merah untuk float 0 --}}
        <div class="card mb-3">
            <div class="card-header">
                <strong><i class="bi bi-table me-1"></i> Rincian Tugas ({{ count($hasil['tugas']) }})</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tugas</th>
                            <th class="text-end">Durasi</th>
                            <th class="text-end">ES</th>
                            <th class="text-end">EF</th>
                            <th class="text-end">LS</th>
                            <th class="text-end">LF</th>
                            <th class="text-end">Float</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hasil['tugas'] as $t)
                            <tr class="{{ $t['kritis'] ? 'table-danger' : '' }}">
                                <td>
                                    @if ($t['kritis'])
                                        <span class="warna-dot me-1" style="background-color: var(--simpro-danger)"></span>
                                    @endif
                                    {{ $t['nama'] }}
                                </td>
                                <td class="text-end">{{ $t['durasi'] }}</td>
                                <td class="text-end">{{ $t['es'] }}</td>
                                <td class="text-end">{{ $t['ef'] }}</td>
                                <td class="text-end">{{ $t['ls'] }}</td>
                                <td class="text-end">{{ $t['lf'] }}</td>
                                <td class="text-end">{{ $t['float'] }}</td>
                                <td>
                                    @if ($t['kritis'])
                                        <span class="badge bg-danger">Kritis</span>
                                    @else
                                        <span class="badge bg-secondary">Normal</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body py-2 small text-muted-simpro border-top">
                ES = earliest start, EF = earliest finish, LS = latest start, LF = latest finish
                (dalam hari kerja sejak awal proyek). Float 0 = tugas kritis.
            </div>
        </div>

        {{-- daftar semua jalur + total durasi; jalur kritis ditandai merah --}}
        <div class="card mb-3">
            <div class="card-header">
                <strong><i class="bi bi-signpost-split me-1"></i> Semua Jalur Awal → Akhir ({{ count($hasil['jalur']) }})</strong>
            </div>
            <ul class="list-group list-group-flush">
                @foreach ($hasil['jalur'] as $jalur)
                    @php $kritis = in_array($jalur['nama'], array_column($hasil['jalur_kritis'], 'nama'), true); @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2 {{ $kritis ? 'text-danger' : '' }}">
                        <span>
                            @if ($kritis)
                                <i class="bi bi-exclamation-octagon-fill me-1"></i>
                            @endif
                            {{ $jalur['nama'] }}
                        </span>
                        <span class="d-flex align-items-center gap-2">
                            <strong>{{ $jalur['total'] }} hari kerja</strong>
                            @if ($kritis)
                                <span class="badge bg-danger">Kritis</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="card-body py-2 small text-muted-simpro border-top">
                Jalur kritis = jalur dengan total terpanjang —
                <strong>{{ $hasil['durasi_total'] }} hari kerja</strong>.
            </div>
        </div>
    @endif
@endsection
