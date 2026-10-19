<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SIMPRO') · Manajemen Proyek</title>

    {{-- Terapkan tema tersimpan sebelum render (anti kedip) --}}
    <script>
        (function () {
            const tema = localStorage.getItem('simpro-tema');
            if (tema) document.documentElement.setAttribute('data-bs-theme', tema);
        })();
    </script>

    {{-- Inter, Bootstrap 5, Bootstrap Icons — semua via CDN, tanpa build --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
</head>
<body>

<div class="app-wrapper">

    {{-- ============ Sidebar (geser di layar kecil) ============ --}}
    <aside class="offcanvas offcanvas-start app-sidebar" tabindex="-1" id="sidebarSimpro"
           aria-labelledby="sidebarLabel">
        <div class="d-flex justify-content-between align-items-center">
            <a class="brand-simpro" href="{{ route('dashboard') }}" id="sidebarLabel">
                <span class="brand-mark"><i class="bi bi-bar-chart-line-fill"></i></span>
                SIMPRO
            </a>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>

        <nav class="mt-2">
            <div class="nav-section">Menu</div>
            <a class="nav-link-simpro @if (request()->routeIs('dashboard')) active @endif"
               href="{{ route('dashboard') }}">
                <i class="bi bi-house-door"></i> Beranda
            </a>
            <a class="nav-link-simpro @if (request()->routeIs('projects.index')) active @endif"
               href="{{ route('projects.index') }}">
                <i class="bi bi-folder2"></i> Daftar Proyek
            </a>
            <a class="nav-link-simpro @if (request()->routeIs('projects.create')) active @endif"
               href="{{ route('projects.create') }}">
                <i class="bi bi-plus-square"></i> Tambah Proyek
            </a>

            {{-- Sub-menu Sumber Daya: satu tautan per proyek (tahap 4A) --}}
            <div class="nav-section">Sumber Daya</div>
            @if (($menuSumberDaya ?? collect())->isEmpty())
                <div class="px-3 small text-muted-simpro">Belum ada proyek</div>
            @else
                @foreach ($menuSumberDaya as $proyekSumberDaya)
                    <a class="nav-link-simpro @if (request()->routeIs('projects.resources.*') && request()->route('project')?->id === $proyekSumberDaya->id) active @endif"
                       href="{{ route('projects.resources.index', $proyekSumberDaya) }}">
                        <i class="bi bi-people"></i> {{ $proyekSumberDaya->name }}
                    </a>
                @endforeach
            @endif

            {{-- Sub-menu Gantt Chart: satu tautan per proyek (seperti Sumber Daya, tahap 5A) --}}
            <div class="nav-section">Gantt Chart</div>
            @if (($menuGantt ?? collect())->isEmpty())
                <div class="px-3 small text-muted-simpro">Belum ada proyek</div>
            @else
                @foreach ($menuGantt as $proyekGantt)
                    <a class="nav-link-simpro @if (request()->routeIs('projects.gantt.*') && request()->route('project')?->id === $proyekGantt->id) active @endif"
                       href="{{ route('projects.gantt.index', $proyekGantt) }}">
                        <i class="bi bi-bar-chart-steps"></i> {{ $proyekGantt->name }}
                    </a>
                @endforeach
            @endif
            {{-- Sub-menu Diagram PERT: satu tautan per proyek (seperti Gantt, tahap 6A) --}}
            <div class="nav-section">Diagram PERT</div>
            @if (($menuPert ?? collect())->isEmpty())
                <div class="px-3 small text-muted-simpro">Belum ada proyek</div>
            @else
                @foreach ($menuPert as $proyekPert)
                    <a class="nav-link-simpro @if (request()->routeIs('projects.pert.*') && request()->route('project')?->id === $proyekPert->id) active @endif"
                       href="{{ route('projects.pert.index', $proyekPert) }}">
                        <i class="bi bi-diagram-3"></i> {{ $proyekPert->name }}
                    </a>
                @endforeach
            @endif
        </nav>

        <div class="position-absolute bottom-0 start-0 end-0 px-4 py-3 text-muted-simpro small border-top">
            <i class="bi bi-kanban"></i> SIMPRO v1.0 — tugas kuliah
        </div>
    </aside>

    {{-- ============ Area utama ============ --}}
    <div class="app-main">

        {{-- Header: judul halaman + aksi utama --}}
        <header class="app-header">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-icon d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#sidebarSimpro" aria-label="Buka menu">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h1>@yield('header-title', 'SIMPRO')</h1>
                    @hasSection('header-subtitle')
                        <p class="page-subtitle">@yield('header-subtitle')</p>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                @yield('header-action')

                {{-- Toggle mode gelap/terang --}}
                <button class="btn btn-icon" type="button" id="toggleTema" aria-label="Ganti mode gelap">
                    <i class="bi bi-moon-stars" id="ikonTema"></i>
                </button>
            </div>
        </header>

        <main class="app-content">
            {{-- Kesalahan validasi global (di samping pesan per-field di bawah input) --}}
            @if ($errors->any())
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                    <div>
                        <strong>Periksa kembali isian Anda.</strong>
                        <ul class="mb-0 mt-1">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- ============ Modal konfirmasi hapus (seragam di semua halaman) ============ --}}
<div class="modal fade" id="modalKonfirmasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold">
                    <i class="bi bi-exclamation-triangle text-danger me-1"></i> Konfirmasi Hapus
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="modalKonfirmasiPesan">Yakin ingin menghapus data ini?</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <form id="modalKonfirmasiForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ============ Toast notifikasi sukses/gagal ============ --}}
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090">
    @if (session('success'))
        <div class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-check-circle me-1"></i> {{ session('success') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
    @if (session('error'))
        <div class="toast align-items-center text-white bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-x-circle me-1"></i> {{ session('error') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ---- Toggle mode gelap, pilihan disimpan di localStorage ----
    (function () {
        const tombol = document.getElementById('toggleTema');
        const ikon = document.getElementById('ikonTema');

        function terapkan(tema) {
            document.documentElement.setAttribute('data-bs-theme', tema);
            localStorage.setItem('simpro-tema', tema);
            ikon.className = tema === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }

        // samakan ikon dengan tema aktif saat halaman dimuat
        terapkan(document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light');

        tombol.addEventListener('click', function () {
            const aktif = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
            terapkan(aktif === 'dark' ? 'light' : 'dark');
        });
    })();

    // ---- Modal konfirmasi: isi pesan & action dari tombol yang diklik ----
    (function () {
        const modal = document.getElementById('modalKonfirmasi');
        modal.addEventListener('show.bs.modal', function (event) {
            const tombol = event.relatedTarget;
            document.getElementById('modalKonfirmasiPesan').textContent =
                tombol.getAttribute('data-pesan') || 'Yakin ingin menghapus data ini?';
            document.getElementById('modalKonfirmasiForm').action = tombol.getAttribute('data-url');
        });
    })();

    // ---- Tampilkan toast notifikasi otomatis ----
    window.addEventListener('load', function () {
        document.querySelectorAll('.toast').forEach(function (el) {
            new bootstrap.Toast(el, { delay: 4000 }).show();
        });
    });
</script>
</body>
</html>
