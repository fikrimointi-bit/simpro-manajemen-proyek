@extends('layouts.app')

@section('title', 'Detail Proyek: ' . $project->name)
@section('header-title', $project->name)
@section('header-subtitle', $project->organization ?: 'Detail proyek')

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
    <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i> Edit Proyek
    </a>
    <button type="button" class="btn btn-outline-danger" title="Hapus proyek"
            data-bs-toggle="modal" data-bs-target="#modalKonfirmasi"
            data-url="{{ route('projects.destroy', $project) }}"
            data-pesan="Hapus proyek ini beserta seluruh tugas, sumber daya, dan hari liburnya?">
        <i class="bi bi-trash"></i>
    </button>
@endsection

@php
    use App\Support\Tanggal;

    $daftarHari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'];
    $weekendDipilih = old('weekend_days', $project->weekend_days ?? []);
@endphp

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $project->name }}</li>
        </ol>
    </nav>

    {{-- Ringkasan proyek --}}
    <div class="card mb-4">
        <div class="card-header"><strong>Ringkasan</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2 mb-md-0">
                    <div class="text-muted-simpro small">Tanggal Mulai</div>
                    <div>{{ Tanggal::panjang($project->start_date) }}</div>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <div class="text-muted-simpro small">Mode Weekend</div>
                    <div>
                        @if ($project->weekend_mode === 'work')
                            <span class="badge bg-warning text-dark">work — weekend tetap kerja</span>
                        @else
                            <span class="badge bg-secondary">no_work — weekend libur</span>
                        @endif
                    </div>
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <div class="text-muted-simpro small">Situs Web</div>
                    <div>
                        @if ($project->website)
                            <a href="{{ $project->website }}" target="_blank" rel="noopener">
                                {{ \Illuminate\Support\Str::limit($project->website, 40) }}
                            </a>
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted-simpro small">Deskripsi</div>
                    <div>{{ $project->description ?: '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        {{-- Kolom kiri: pengaturan weekend --}}
        <div class="col-lg-5 mb-4">
            <div class="card h-100">
                <div class="card-header"><strong><i class="bi bi-calendar2-week me-1"></i> Pengaturan Weekend</strong></div>
                <div class="card-body">
                    <form action="{{ route('projects.weekend.update', $project) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <div class="form-label">Hari weekend</div>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach ($daftarHari as $angka => $nama)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="weekend_days[]"
                                               value="{{ $angka }}" id="weekend-{{ $angka }}"
                                               @checked(in_array($angka, array_map('intval', $weekendDipilih)))>
                                        <label class="form-check-label" for="weekend-{{ $angka }}">{{ $nama }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="weekend_mode" class="form-label">Mode</label>
                            <select name="weekend_mode" id="weekend_mode" class="form-select">
                                <option value="no_work" @selected(old('weekend_mode', $project->weekend_mode) === 'no_work')>
                                    no_work — hari weekend diliburkan
                                </option>
                                <option value="work" @selected(old('weekend_mode', $project->weekend_mode) === 'work')>
                                    work — hari weekend tetap dihitung hari kerja
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Kolom kanan: kelola hari libur --}}
        <div class="col-lg-7 mb-4">
            <div class="card mb-4">
                <div class="card-header"><strong><i class="bi bi-calendar2-plus me-1"></i> Tambah Hari Libur</strong></div>
                <div class="card-body">
                    <form action="{{ route('holidays.store', $project) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label for="holiday-date" class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="holiday-date" class="form-control @error('date') is-invalid @enderror"
                                   required value="{{ old('date') }}">
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="holiday-description" class="form-label">Keterangan</label>
                            <input type="text" name="description" id="holiday-description" class="form-control"
                                   placeholder="Contoh: Hari Kemerdekaan" value="{{ old('description') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Jenis <span class="text-danger">*</span></label>
                            <select name="type" class="form-select">
                                <option value="sekali" @selected(old('type') === 'sekali')>Sekali</option>
                                <option value="berulang" @selected(old('type') === 'berulang')>Berulang</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Tambah</button>
                        </div>
                        <div class="col-12">
                            <div class="form-text">
                                Jenis <strong>Sekali</strong> hanya berlaku pada tahun berjalan;
                                <strong>Berulang</strong> aktif setiap tahun pada bulan + tanggal yang sama.
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Daftar Hari Libur ({{ $holidays->count() }})</strong></div>
                @if ($holidays->isEmpty())
                    <div class="empty-state py-4">
                        <i class="bi bi-calendar2"></i>
                        <h5 class="h6">Belum ada hari libur</h5>
                        <p class="small mb-0">Tambahkan libur sekali pakai atau berulang tiap tahun.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Keterangan</th>
                                    <th>Jenis</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($holidays as $holiday)
                                    <tr>
                                        <td>{{ Tanggal::panjang($holiday->date) }}</td>
                                        <td>{{ $holiday->description ?: '—' }}</td>
                                        <td>
                                            @if ($holiday->is_recurring)
                                                <span class="badge bg-warning text-dark">Berulang tiap tahun</span>
                                            @else
                                                <span class="badge bg-secondary">Sekali</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                                    data-bs-toggle="modal" data-bs-target="#modalKonfirmasi"
                                                    data-url="{{ route('holidays.destroy', [$project, $holiday]) }}"
                                                    data-pesan="Hari libur &laquo;{{ Tanggal::panjang($holiday->date) }}&raquo; akan dihapus. Lanjutkan?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
