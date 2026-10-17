@extends('layouts.app')

@section('title', 'Tugas: ' . $project->name)
@section('header-title', 'Daftar Tugas')
@section('header-subtitle', $project->name)

@section('header-action')
    <a href="{{ route('projects.tasks.create', $project) }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Tugas Induk
    </a>
@endsection

@php
    use App\Support\Tanggal;

    $badgePrioritas = [
        'rendah' => ['class' => 'prioritas-rendah', 'label' => 'Rendah'],
        'normal' => ['class' => 'prioritas-normal', 'label' => 'Normal'],
        'tinggi' => ['class' => 'prioritas-tinggi', 'label' => 'Tinggi'],
    ];
@endphp

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Tugas</li>
        </ol>
    </nav>

    @if (count($baris) === 0)
        <div class="card">
            <div class="empty-state">
                <i class="bi bi-list-task"></i>
                <h5>Belum ada tugas</h5>
                <p>Buat tugas induk lalu tambahkan sub-tugas di bawahnya — jadwalnya dihitung otomatis dari hari kerja proyek.</p>
                <a href="{{ route('projects.tasks.create', $project) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Tugas Induk
                </a>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nama Tugas</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th class="text-end">Durasi (hari kerja)</th>
                            <th>Predecessor</th>
                            <th>Penanggung Jawab</th>
                            <th>Prioritas</th>
                            <th>Warna</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $b)
                            @php
                                $task = $b['task'];
                                $anak = $jumlahAnak->get($task->id, 0);
                                $badge = $badgePrioritas[$task->priority] ?? $badgePrioritas['normal'];

                                // format "Analisis Kebutuhan (SS+3)": pendahulu (tipe+delay hari kerja)
                                $teksPredecessor = $task->predecessors
                                    ->filter(fn ($d) => $d->predecessor)
                                    ->map(fn ($d) => $d->predecessor->name
                                        . ' (' . $d->type . ($d->lag_days >= 0 ? '+' : '') . $d->lag_days . ')')
                                    ->implode(', ');
                            @endphp
                            <tr>
                                <td style="padding-left: {{ 16 + $b['depth'] * 28 }}px">
                                    @if ($b['depth'] > 0)
                                        <span class="text-muted-simpro me-1">└</span>
                                    @endif
                                    <span class="fw-semibold">{{ $task->name }}</span>
                                    @if ($anak > 0)
                                        <span class="badge bg-secondary ms-1">{{ $anak }} sub-tugas</span>
                                    @endif
                                </td>
                                {{-- tugas induk: tanggal & durasi hasil hitungan otomatis --}}
                                <td>{{ $task->start_date ? Tanggal::pendek($task->start_date) : '—' }}</td>
                                <td>{{ $task->end_date ? Tanggal::pendek($task->end_date) : '—' }}</td>
                                <td class="text-end">{{ $task->duration ?? '—' }}</td>
                                <td class="text-nowrap">{{ $teksPredecessor !== '' ? $teksPredecessor : '—' }}</td>
                                {{-- penugasan anggota (tahap 4B): badge nama + peringatan bentrok cuti --}}
                                <td>
                                    @if ($task->resources->isEmpty())
                                        <span class="text-muted-simpro">—</span>
                                    @else
                                        @foreach ($task->resources as $penugasan)
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle me-1">
                                                <i class="bi bi-person-fill me-1"></i>{{ $penugasan->name }}
                                            </span>
                                        @endforeach
                                    @endif

                                    @foreach ($bentrokCuti[$task->id] ?? [] as $peringatan)
                                        <div class="small text-warning mt-1" role="alert">
                                            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                                            Bentrok cuti: {{ $peringatan['anggota'] }} ({{ $peringatan['cuti'] }})
                                        </div>
                                    @endforeach
                                </td>
                                <td><span class="badge {{ $badge['class'] }}">{{ $badge['label'] }}</span></td>
                                <td>
                                    <span class="warna-dot"
                                          style="background-color: {{ $task->color ?: '#6c757d' }}"></span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($anak === 0)
                                        <a href="{{ route('projects.tasks.create', [$project, 'parent_id' => $task->id]) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Tambah sub-tugas">
                                            <i class="bi bi-plus-lg"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('projects.tasks.edit', [$project, $task]) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                            data-bs-toggle="modal" data-bs-target="#modalKonfirmasi"
                                            data-url="{{ route('projects.tasks.destroy', [$project, $task]) }}"
                                            data-pesan="{{ $anak > 0
                                                ? 'Hapus tugas «' . $task->name . '» beserta ' . $anak . ' sub-tugas di bawahnya?'
                                                : 'Hapus tugas «' . $task->name . '»?' }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted-simpro small mt-2">
            Tanggal selesai tugas anak = hari kerja terakhir sesuai durasi.
            Tugas induk mengambil mulai paling awal dan selesai paling akhir anaknya.
        </p>
    @endif
@endsection
