{{-- Satu baris isian data kustom (tahap 4A): nama kolom + nilai per anggota. --}}
{{-- $index angka biasa saat dirender dari data, '__IDX__' saat dipakai sebagai template JS. --}}
@php
    $labelKustom = old("custom_fields.$index.label", $baris['label'] ?? '');
    $nilaiKustom = old("custom_fields.$index.value", $baris['value'] ?? '');
@endphp

<div class="row g-2 align-items-start" data-baris-kustom>
    <div class="col-md-5">
        <input type="text" name="custom_fields[{{ $index }}][label]"
               class="form-control @error("custom_fields.$index.label") is-invalid @enderror"
               placeholder="Nama kolom (contoh: NIP)" aria-label="Nama kolom"
               value="{{ $labelKustom }}">
        @error("custom_fields.$index.label")
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6">
        <input type="text" name="custom_fields[{{ $index }}][value]"
               class="form-control @error("custom_fields.$index.value") is-invalid @enderror"
               placeholder="Nilai" aria-label="Nilai kolom"
               value="{{ $nilaiKustom }}">
        @error("custom_fields.$index.value")
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-1 d-flex justify-content-end">
        <button type="button" class="btn btn-outline-danger btn-sm" data-hapus-kustom
                title="Hapus baris" aria-label="Hapus baris data kustom">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</div>
