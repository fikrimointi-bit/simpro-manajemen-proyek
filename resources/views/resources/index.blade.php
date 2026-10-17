@extends('layouts.app')

@section('title', 'Sumber Daya: ' . $project->name)
@section('header-title', 'Sumber Daya')
@section('header-subtitle', $project->name)

@section('header-action')
    <a href="{{ route('projects.resources.create', $project) }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Tambah Anggota
    </a>
@endsection

@php
    // format Rupiah tampil: Rp 3.500.000 (tanda '—' bila gaji tidak diisi)
    $rupiah = fn ($gaji): string => $gaji === null ? '—' : 'Rp ' . number_format($gaji, 0, ',', '.');
@endphp

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Sumber Daya</li>
        </ol>
    </nav>

    @if ($anggota->isEmpty())
        <div class="card">
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <h5>Belum ada anggota</h5>
                <p>Tambahkan anggota tim beserta peran, gaji, dan data kustomnya.</p>
                <a href="{{ route('projects.resources.create', $project) }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Tambah Anggota
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
                            <th>Telepon</th>
                            <th>Email</th>
                            <th>Peran</th>
                            <th class="text-end">Gaji / Bulan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anggota as $sumberDaya)
                            <tr>
                                <td>
                                    <a href="{{ route('projects.resources.show', [$project, $sumberDaya]) }}"
                                       class="fw-semibold text-decoration-none">
                                        {{ $sumberDaya->name }}
                                    </a>
                                    @if ($sumberDaya->custom_fields)
                                        <div class="small text-muted-simpro">
                                            {{ count($sumberDaya->custom_fields) }} data kustom
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $sumberDaya->phone ?: '—' }}</td>
                                <td>{{ $sumberDaya->email ?: '—' }}</td>
                                <td>{{ $sumberDaya->role ?: '—' }}</td>
                                <td class="text-end text-nowrap">{{ $rupiah($sumberDaya->salary) }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('projects.resources.show', [$project, $sumberDaya]) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('projects.resources.edit', [$project, $sumberDaya]) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                            data-bs-toggle="modal" data-bs-target="#modalKonfirmasi"
                                            data-url="{{ route('projects.resources.destroy', [$project, $sumberDaya]) }}"
                                            data-pesan="Hapus anggota &laquo;{{ $sumberDaya->name }}&raquo; beserta cuti dan penugasannya?">
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
