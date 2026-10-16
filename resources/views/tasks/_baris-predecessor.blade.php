{{-- Satu baris isian predecessor (tahap 3B): pendahulu, tipe + keterangan, delay. --}}
{{-- $index angka biasa saat dirender dari data, '__IDX__' saat dipakai sebagai template JS. --}}
@php
    $predId = old("predecessors.$index.predecessor_id", $baris['predecessor_id'] ?? '');
    $predTipe = old("predecessors.$index.type", $baris['type'] ?? 'FS');
    $predDelay = old("predecessors.$index.lag_days", $baris['lag_days'] ?? 0) ?? 0;
    $tipeDanKeterangan = [
        'FS' => 'Selesai → Mulai',
        'SS' => 'Mulai → Mulai',
        'FF' => 'Selesai → Selesai',
        'SF' => 'Mulai → Selesai',
    ];
@endphp

<div class="row g-2 align-items-start" data-baris-predecessor>
    <div class="col-md-5">
        <select name="predecessors[{{ $index }}][predecessor_id]" data-pred-id
                class="form-select @error("predecessors.$index.predecessor_id") is-invalid @enderror"
                aria-label="Tugas pendahulu">
            <option value="">— Pilih tugas pendahulu —</option>
            @foreach ($kandidatPendahulu as $kandidat)
                <option value="{{ $kandidat->id }}" @selected((string) $predId === (string) $kandidat->id)>
                    {{ $kandidat->name }}
                </option>
            @endforeach
        </select>
        @error("predecessors.$index.predecessor_id")
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4">
        <select name="predecessors[{{ $index }}][type]"
                class="form-select @error("predecessors.$index.type") is-invalid @enderror"
                aria-label="Tipe dependensi">
            @foreach ($tipeDanKeterangan as $kode => $keterangan)
                <option value="{{ $kode }}" @selected((string) $predTipe === $kode)>
                    {{ $kode }} — {{ $keterangan }}
                </option>
            @endforeach
        </select>
        @error("predecessors.$index.type")
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-2">
        <input type="number" name="predecessors[{{ $index }}][lag_days]"
               class="form-control @error("predecessors.$index.lag_days") is-invalid @enderror"
               min="-3660" max="3660" value="{{ $predDelay }}" aria-label="Delay hari kerja">
        @error("predecessors.$index.lag_days")
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-1 d-flex justify-content-end">
        <button type="button" class="btn btn-outline-danger btn-sm" data-hapus-predecessor
                title="Hapus baris" aria-label="Hapus baris predecessor">
            <i class="bi bi-trash"></i>
        </button>
    </div>
</div>
