{{-- Form bersama buat/edit tugas --}}
@php
    // tugas induk (punya sub-tugas) tidak mengisi tanggal & durasi
    $isInduk = isset($task) && $task->isInduk();
    $tampilkanJadwal = ! $isInduk;

    // baris predecessor: gagal validasi memakai isian lama, selain itu data tersimpan
    $barisPred = old('predecessor_form') !== null
        ? (old('predecessors') ?? [])
        : $pendahuluTersimpan;
    $barisPred = is_array($barisPred) ? $barisPred : [];
    $adaPred = collect($barisPred)->contains(
        fn ($b): bool => is_array($b) && ! empty($b['predecessor_id'])
    );
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
                   value="{{ old('start_date', isset($task) && $task->start_date ? $task->start_date->format('Y-m-d') : '') }}"
                   @disabled($adaPred)>
            <div class="form-text {{ $adaPred ? '' : 'd-none' }}" id="catatan-predecessor">Dihitung otomatis dari predecessor</div>
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

    {{-- ============ Predecessor (tahap 3B): baris dinamis, tanpa reload ============ --}}
    <div class="mb-2" id="bagian-predecessor">
        <input type="hidden" name="predecessor_form" value="1">
        <label class="form-label fw-semibold mb-1">Predecessor</label>

        <div class="row g-2 small text-muted mb-1 d-none d-md-flex">
            <div class="col-md-5">Tugas Pendahulu</div>
            <div class="col-md-4">Tipe</div>
            <div class="col-md-2">Delay (hari kerja)</div>
            <div class="col-md-1"></div>
        </div>

        <div id="daftar-predecessor" class="d-flex flex-column gap-2">
            @foreach ($barisPred as $index => $baris)
                @include('tasks._baris-predecessor', ['index' => $index, 'baris' => $baris])
            @endforeach
        </div>

        <template id="tpl-predecessor">
            @include('tasks._baris-predecessor', ['index' => '__IDX__', 'baris' => []])
        </template>

        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="tambah-predecessor">
            <i class="bi bi-plus-lg me-1"></i> Tambah Predecessor
        </button>
        <div class="form-text">
            Hanya tugas tanpa sub-tugas di proyek ini; delay dalam hari kerja (boleh negatif, default 0).
            Satu baris = satu pendahulu.
        </div>
    </div>

    <script>
        (function () {
            const daftar = document.getElementById('daftar-predecessor');
            const tpl = document.getElementById('tpl-predecessor');
            const tombolTambah = document.getElementById('tambah-predecessor');
            let indeks = {{ count($barisPred) }};

            // Nonaktifkan tanggal mulai selama ada predecessor (dihitung otomatis)
            function sinkronTanggalMulai() {
                const input = document.getElementById('task-start');
                const catatan = document.getElementById('catatan-predecessor');
                if (!input || !daftar) return;

                const ada = Array.from(daftar.querySelectorAll('[data-pred-id]'))
                    .some((sel) => sel.value !== '');

                input.disabled = ada;
                if (catatan) catatan.classList.toggle('d-none', !ada);
            }

            if (tombolTambah && daftar && tpl) {
                tombolTambah.addEventListener('click', function () {
                    daftar.insertAdjacentHTML(
                        'beforeend',
                        tpl.innerHTML.replaceAll('__IDX__', String(indeks++))
                    );
                    sinkronTanggalMulai();
                });
            }

            if (daftar) {
                daftar.addEventListener('click', function (event) {
                    const tombol = event.target.closest('[data-hapus-predecessor]');
                    if (!tombol) return;
                    tombol.closest('[data-baris-predecessor]').remove();
                    sinkronTanggalMulai();
                });

                daftar.addEventListener('change', function (event) {
                    if (event.target.matches('[data-pred-id]')) sinkronTanggalMulai();
                });
            }

            sinkronTanggalMulai();
        })();
    </script>
@else
    <div class="alert alert-info mb-0">
        <i class="bi bi-info-circle me-1"></i>
        Tugas ini memiliki sub-tugas, jadi <strong>tanggal mulai, selesai, dan durasinya dihitung otomatis</strong>
        dari sub-tugas (mulai paling awal – selesai paling akhir).
    </div>
@endif
