{{-- Form bersama buat/edit tugas --}}
@php
    // tugas induk (punya sub-tugas) tidak mengisi tanggal & durasi
    $isInduk = isset($task) && $task->isInduk();
    $tampilkanJadwal = ! $isInduk;
@endphp

<div class="mb-3">
    <label for="task-name" class="form-label">Nama Tugas <span class="text-danger">*</span></label>
    <input type="text" name="name" id="task-name" class="form-control @error('name') is-invalid @enderror" required
           value="{{ old('name', $task->name ?? '') }}" placeholder="Contoh: Persiapan konsep acara">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="task-priority" class="form-label">Prioritas <span class="text-danger">*</span></label>
        <select name="priority" id="task-priority" class="form-select @error('priority') is-invalid @enderror">
            <option value="rendah" @selected(old('priority', $task->priority ?? 'normal') === 'rendah')>Rendah</option>
            <option value="normal" @selected(old('priority', $task->priority ?? 'normal') === 'normal')>Normal</option>
            <option value="tinggi" @selected(old('priority', $task->priority ?? 'normal') === 'tinggi')>Tinggi</option>
        </select>
        @error('priority')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="task-color" class="form-label">Warna</label>
        <input type="color" name="color" id="task-color" class="form-control form-control-color w-100"
               value="{{ old('color', $task->color ?? '#3b82f6') }}" title="Pilih warna batang tugas">
    </div>
    <div class="col-md-4 mb-3">
        <label for="task-parent" class="form-label">Tugas Induk</label>
        <select name="parent_id" id="task-parent" class="form-select @error('parent_id') is-invalid @enderror">
            <option value="">— Tidak ada (tugas induk/mandiri) —</option>
            @foreach ($tugasLain as $lain)
                <option value="{{ $lain->id }}"
                    @selected((string) old('parent_id', $task->parent_id ?? $parent->id ?? '') === (string) $lain->id)>
                    {{ $lain->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Kosongkan untuk tugas induk; pilih induk untuk membuat sub-tugas.</div>
        @error('parent_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

@if ($tampilkanJadwal)
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="task-start" class="form-label">Tanggal Mulai</label>
            <input type="date" name="start_date" id="task-start" class="form-control @error('start_date') is-invalid @enderror"
                   value="{{ old('start_date', isset($task) && $task->start_date ? $task->start_date->format('Y-m-d') : '') }}">
            @error('start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label for="task-duration" class="form-label">Durasi (hari kerja)</label>
            <input type="number" name="duration" id="task-duration" class="form-control @error('duration') is-invalid @enderror"
                   min="1" max="3660" value="{{ old('duration', $task->duration ?? '') }}">
            <div class="form-text">Tanggal selesai dihitung otomatis dari hari kerja proyek.</div>
            @error('duration')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-12">
            <div class="form-text mb-2">
                Kosongkan <strong>kedua</strong> isian di atas jika tugas ini akan menjadi induk
                (jadwalnya dihitung otomatis dari sub-tugas). Isian harus lengkap berduaan.
            </div>
        </div>
    </div>
@else
    <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Tugas ini memiliki sub-tugas, jadi <strong>tanggal mulai, selesai, dan durasinya dihitung otomatis</strong>
        dari sub-tugas (mulai paling awal – selesai paling akhir).
    </div>
@endif
