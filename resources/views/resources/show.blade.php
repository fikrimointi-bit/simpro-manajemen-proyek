@extends('layouts.app')

@section('title', 'Detail Anggota: ' . $anggota->name)
@section('header-title', $anggota->name)
@section('header-subtitle', 'Detail anggota — ' . $project->name)

@section('header-action')
    <a href="{{ route('projects.resources.edit', [$project, $anggota]) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i> Edit Anggota
    </a>
    <button type="button" class="btn btn-outline-danger" title="Hapus anggota"
            data-bs-toggle="modal" data-bs-target="#modalKonfirmasi"
            data-url="{{ route('projects.resources.destroy', [$project, $anggota]) }}"
            data-pesan="Hapus anggota &laquo;{{ $anggota->name }}&raquo; beserta cuti dan penugasannya?">
        <i class="bi bi-trash"></i>
    </button>
@endsection

@php
    use App\Support\Tanggal;

    // format Rupiah tampil: Rp 3.500.000 (tanda '—' bila gaji tidak diisi)
    $rupiah = fn ($gaji): string => $gaji === null ? '—' : 'Rp ' . number_format($gaji, 0, ',', '.');
    $kustom = $anggota->custom_fields ?? [];
@endphp

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.resources.index', $project) }}">Sumber Daya</a></li>
            <li class="breadcrumb-item active" aria-current="page">Detail</li>
        </ol>
    </nav>

    <div class="row">
        {{-- Kolom kiri: data pribadi + data kustom --}}
        <div class="col-lg-5 mb-4">
            <div class="card mb-4">
                <div class="card-header"><strong><i class="bi bi-person me-1"></i> Data Anggota</strong></div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-5 text-muted-simpro small">Telepon</div>
                        <div class="col-7">{{ $anggota->phone ?: '—' }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-5 text-muted-simpro small">Email</div>
                        <div class="col-7">{{ $anggota->email ?: '—' }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-5 text-muted-simpro small">Peran</div>
                        <div class="col-7">{{ $anggota->role ?: '—' }}</div>
                    </div>
                    <div class="row mb-0">
                        <div class="col-5 text-muted-simpro small">Gaji / Bulan</div>
                        <div class="col-7">{{ $rupiah($anggota->salary) }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong><i class="bi bi-list-check me-1"></i> Data Kustom</strong></div>
                @if (count($kustom) === 0)
                    <div class="empty-state py-4">
                        <i class="bi bi-ui-checks-grid"></i>
                        <h5 class="h6">Belum ada data kustom</h5>
                        <p class="small mb-0">Tambahkan pasangan nama kolom dan nilai lewat form edit anggota.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nama Kolom</th>
                                    <th>Nilai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($kustom as $baris)
                                    <tr>
                                        <td class="fw-semibold">{{ $baris['label'] ?? '—' }}</td>
                                        <td>{{ $baris['value'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Kolom kanan: cuti pribadi --}}
        <div class="col-lg-7 mb-4">
            <div class="card mb-4">
                <div class="card-header"><strong><i class="bi bi-calendar2-plus me-1"></i> Tambah Cuti Pribadi</strong></div>
                <div class="card-body">
                    <form action="{{ route('resource-days-off.store', [$project, $anggota]) }}" method="POST" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label for="cuti-dari" class="form-label">Dari <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="cuti-dari" class="form-control @error('date') is-invalid @enderror"
                                   required value="{{ old('date') }}">
                            @error('date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4">
                            <label for="cuti-sampai" class="form-label">Sampai <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" id="cuti-sampai" class="form-control @error('end_date') is-invalid @enderror"
                                   required value="{{ old('end_date') }}">
                            @error('end_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label for="cuti-keterangan" class="form-label">Keterangan</label>
                            <input type="text" name="description" id="cuti-keterangan" class="form-control"
                                   placeholder="Contoh: Cuti keluarga" value="{{ old('description') }}" maxlength="255">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary w-100">Tambah</button>
                        </div>
                        <div class="col-12">
                            <div class="form-text">Tanggal sampai tidak boleh lebih awal dari tanggal mulai.</div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Daftar Cuti Pribadi ({{ $cuti->count() }})</strong></div>
                @if ($cuti->isEmpty())
                    <div class="empty-state py-4">
                        <i class="bi bi-calendar2"></i>
                        <h5 class="h6">Belum ada cuti</h5>
                        <p class="small mb-0">Tambahkan rentang cuti pribadi anggota ini.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Dari</th>
                                    <th>Sampai</th>
                                    <th>Keterangan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cuti as $hariCuti)
                                    <tr>
                                        <td>{{ Tanggal::pendek($hariCuti->date) }}</td>
                                        <td>{{ $hariCuti->end_date ? Tanggal::pendek($hariCuti->end_date) : Tanggal::pendek($hariCuti->date) }}</td>
                                        <td>{{ $hariCuti->description ?: '—' }}</td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                                    data-bs-toggle="modal" data-bs-target="#modalKonfirmasi"
                                                    data-url="{{ route('resource-days-off.destroy', [$project, $anggota, $hariCuti]) }}"
                                                    data-pesan="Cuti {{ Tanggal::pendek($hariCuti->date) }}&ndash;{{ Tanggal::pendek($hariCuti->end_date ?? $hariCuti->date) }} akan dihapus. Lanjutkan?">
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
