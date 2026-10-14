{{-- Form bersama buat/edit proyek --}}
@php
    $daftarHari = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 0 => 'Minggu'];
    $weekendDipilih = old('weekend_days', $project->weekend_days ?? [6, 0]);
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Nama Proyek <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" required
           value="{{ old('name', $project->name ?? '') }}" placeholder="Contoh: Perencanaan Festival Kuliah">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="organization" class="form-label">Organisasi</label>
        <input type="text" name="organization" id="organization" class="form-control"
               value="{{ old('organization', $project->organization ?? '') }}" placeholder="Contoh: BEM Fakultas">
    </div>
    <div class="col-md-6 mb-3">
        <label for="website" class="form-label">Situs Web</label>
        <input type="url" name="website" id="website" class="form-control @error('website') is-invalid @enderror"
               value="{{ old('website', $project->website ?? '') }}" placeholder="https://contoh.ac.id">
        @error('website')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3"
              placeholder="Ringkasan singkat proyek">{{ old('description', $project->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="start_date" class="form-label">Tanggal Mulai <span class="text-danger">*</span></label>
    <input type="date" name="start_date" id="start_date" class="form-control @error('start_date') is-invalid @enderror" required
           value="{{ old('start_date', isset($project) && $project->start_date ? $project->start_date->format('Y-m-d') : '') }}">
    @error('start_date')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<fieldset class="mb-3">
    <legend class="form-label">Hari Weekend</legend>
    <div class="d-flex flex-wrap gap-3">
        @foreach ($daftarHari as $angka => $nama)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="weekend_days[]" value="{{ $angka }}"
                       id="weekend-{{ $angka }}"
                       @checked(in_array($angka, array_map('intval', $weekendDipilih)))>
                <label class="form-check-label" for="weekend-{{ $angka }}">{{ $nama }}</label>
            </div>
        @endforeach
    </div>
</fieldset>

<div class="mb-3">
    <label for="weekend_mode" class="form-label">Mode Weekend <span class="text-danger">*</span></label>
    <select name="weekend_mode" id="weekend_mode" class="form-select @error('weekend_mode') is-invalid @enderror">
        <option value="no_work" @selected(old('weekend_mode', $project->weekend_mode ?? 'no_work') === 'no_work')>
            no_work — hari weekend diliburkan
        </option>
        <option value="work" @selected(old('weekend_mode', $project->weekend_mode ?? 'no_work') === 'work')>
            work — hari weekend tetap dihitung hari kerja
        </option>
    </select>
    @error('weekend_mode')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
