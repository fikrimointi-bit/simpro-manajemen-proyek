{{-- Form bersama buat/edit anggota (tahap 4A) --}}
@php
    // baris data kustom: gagal validasi memakai isian lama, selain itu data tersimpan
    $barisKustom = old('custom_fields') ?? ($resource->custom_fields ?? []);
    $barisKustom = is_array($barisKustom) ? $barisKustom : [];
@endphp

<div class="mb-3">
    <label for="res-name" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" name="name" id="res-name" class="form-control @error('name') is-invalid @enderror" required
           value="{{ old('name', $resource->name ?? '') }}" placeholder="Contoh: Budi Santoso">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="res-phone" class="form-label">Telepon</label>
        <input type="text" name="phone" id="res-phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $resource->phone ?? '') }}" placeholder="Contoh: 081234567890" maxlength="30">
        @error('phone')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="res-email" class="form-label">Email</label>
        <input type="email" name="email" id="res-email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $resource->email ?? '') }}" placeholder="contoh@kampus.ac.id">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="res-role" class="form-label">Peran</label>
        <input type="text" name="role" id="res-role" class="form-control @error('role') is-invalid @enderror"
               value="{{ old('role', $resource->role ?? '') }}" placeholder="Contoh: Koordinator Lapangan">
        @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label for="res-salary" class="form-label">Gaji (Rp / bulan)</label>
        <input type="number" name="salary" id="res-salary" class="form-control @error('salary') is-invalid @enderror"
               min="0" step="1" value="{{ old('salary', $resource->salary ?? '') }}" placeholder="Contoh: 3500000">
        <div class="form-text">Isi dalam Rupiah tanpa titik; contoh 3500000 tampil sebagai Rp 3.500.000.</div>
        @error('salary')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

{{-- ============ Data kustom: baris dinamis, tanpa reload ============ --}}
<div class="mb-3" id="bagian-kustom">
    <label class="form-label fw-semibold mb-1">Data Kustom</label>

    <div class="row g-2 small text-muted mb-1 d-none d-md-flex">
        <div class="col-md-5">Nama Kolom</div>
        <div class="col-md-6">Nilai</div>
        <div class="col-md-1"></div>
    </div>

    <div id="daftar-kustom" class="d-flex flex-column gap-2">
        @foreach ($barisKustom as $index => $baris)
            @include('resources._baris-kustom', ['index' => $index, 'baris' => $baris])
        @endforeach
    </div>

    <template id="tpl-kustom">
        @include('resources._baris-kustom', ['index' => '__IDX__', 'baris' => []])
    </template>

    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="tambah-kustom">
        <i class="bi bi-plus-lg me-1"></i> Tambah Data Kustom
    </button>
    <div class="form-text">
        Pasangan nama kolom dan nilai khusus anggota ini (contoh: NIP, divisi lama).
        Disimpan sebagai JSON dan ditampilkan di halaman detail anggota.
    </div>
</div>

<script>
    (function () {
        const daftar = document.getElementById('daftar-kustom');
        const tpl = document.getElementById('tpl-kustom');
        const tombolTambah = document.getElementById('tambah-kustom');
        let indeks = {{ count($barisKustom) }};

        if (tombolTambah && daftar && tpl) {
            tombolTambah.addEventListener('click', function () {
                daftar.insertAdjacentHTML(
                    'beforeend',
                    tpl.innerHTML.replaceAll('__IDX__', String(indeks++))
                );
            });
        }

        if (daftar) {
            daftar.addEventListener('click', function (event) {
                const tombol = event.target.closest('[data-hapus-kustom]');
                if (tombol) tombol.closest('[data-baris-kustom]').remove();
            });
        }
    })();
</script>
