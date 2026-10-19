@extends('layouts.app')

@section('title', 'Diagram PERT: ' . $project->name)
@section('header-title', 'Diagram PERT')
@section('header-subtitle', $project->name)

@section('header-action')
    <a href="{{ route('projects.tasks.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-list-task me-1"></i> Kelola Tugas
    </a>
    <a href="{{ route('projects.gantt.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-bar-chart-steps me-1"></i> Gantt Chart
    </a>
    <a href="{{ route('projects.edit', $project) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i> Edit Proyek
    </a>
@endsection

@section('content')
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Proyek</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Diagram PERT</li>
        </ol>
    </nav>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-diagram-3 me-1"></i> Diagram PERT (Activity-on-Node)</strong>
            <a href="{{ route('projects.pert.data', $project) }}" class="btn btn-sm btn-outline-secondary"
               target="_blank" rel="noopener">
                <i class="bi bi-code-slash me-1"></i> Lihat JSON
            </a>
        </div>

        {{-- status memuat data JSON --}}
        <div id="pert-muat" class="empty-state">
            <i class="bi bi-hourglass-split"></i>
            <h5>Memuat diagram…</h5>
            <p class="mb-0">Mengambil node dan layer dari data PERT proyek.</p>
        </div>

        {{-- kotak diagram: node per layer, di-scroll bila lebar --}}
        <div id="pert-kotak" class="d-none">
            <div class="pert-scroll">
                <svg id="pert-svg" xmlns="http://www.w3.org/2000/svg"
                     role="img" aria-label="Diagram PERT"></svg>
            </div>
        </div>

        {{-- empty state bila proyek belum punya tugas leaf --}}
        <div id="pert-kosong" class="empty-state d-none">
            <i class="bi bi-diagram-3"></i>
            <h5>Belum ada tugas</h5>
            <p>Tambahkan tugas dulu agar node PERT tampil di diagram.</p>
            <a href="{{ route('projects.tasks.create', $project) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Tambah Tugas
            </a>
        </div>
    </div>

    {{-- ============ Diagram PERT: SVG + JavaScript biasa, tanpa library (tahap 6B) ============ --}}
    <script>
    (function () {
        const URL_DATA = @js(route('projects.pert.data', $project));
        const SVG_NS = 'http://www.w3.org/2000/svg';

        // ukuran node (px) — kotak tugas lega, kapsul Mulai/Selesai kecil
        const LEBAR_NODE = 190;
        const TINGGI_NODE = 80;
        const LEBAR_KAPSUL = 100;
        const TINGGI_KAPSUL = 36;
        const JEDA_X = 100;   // jarak antar kolom (layer)
        const JEDA_Y = 28;    // jarak antar node dalam satu kolom
        const PADDING = 28;   // tepi luar diagram

        const blokMuat = document.getElementById('pert-muat');
        const blokKosong = document.getElementById('pert-kosong');
        const kotak = document.getElementById('pert-kotak');
        const svg = document.getElementById('pert-svg');

        function el(tag, attr, teks) {
            const node = document.createElementNS(SVG_NS, tag);
            Object.keys(attr || {}).forEach((k) => node.setAttribute(k, attr[k]));
            if (teks !== undefined) node.textContent = teks;
            return node;
        }

        function tampilkan(data) {
            const node = data.node || [];
            const tugas = node.filter((n) => n.id !== 'mulai' && n.id !== 'selesai');
            if (!tugas.length) {
                blokKosong.classList.remove('d-none');
                return;
            }

            // kelompokkan node per layer (kolom), lalu urutkan per kolom berdasarkan id
            const perLayer = new Map();
            node.forEach((n) => {
                const l = Number(n.layer) || 0;
                if (!perLayer.has(l)) perLayer.set(l, []);
                perLayer.get(l).push(n);
            });
            const layers = [...perLayer.keys()].sort((a, b) => a - b);
            layers.forEach((l) => perLayer.get(l).sort((a, b) => String(a.id).localeCompare(String(b.id), 'id')));

            // tinggi kolom tertinggi = tinggi seluruh diagram (kolom dipusatkan vertikal)
            const tinggiKolom = (l) => {
                const kolom = perLayer.get(l);
                return kolom.length * TINGGI_NODE + (kolom.length - 1) * JEDA_Y;
            };
            let tinggiTotal = 0;
            layers.forEach((l) => { tinggiTotal = Math.max(tinggiTotal, tinggiKolom(l)); });
            tinggiTotal += PADDING * 2;

            const lebarTotal = PADDING * 2 + layers.length * LEBAR_NODE + (layers.length - 1) * JEDA_X;

            svg.setAttribute('width', lebarTotal);
            svg.setAttribute('height', tinggiTotal);
            svg.setAttribute('viewBox', `0 0 ${lebarTotal} ${tinggiTotal}`);
            while (svg.firstChild) svg.removeChild(svg.firstChild);

            layers.forEach((l, kolom) => {
                const isi = perLayer.get(l);
                const x = PADDING + kolom * (LEBAR_NODE + JEDA_X);
                const y0 = PADDING + (tinggiTotal - PADDING * 2 - tinggiKolom(l)) / 2;

                isi.forEach((n, i) => {
                    const kapsul = n.id === 'mulai' || n.id === 'selesai';
                    const w = kapsul ? LEBAR_KAPSUL : LEBAR_NODE;
                    const h = kapsul ? TINGGI_KAPSUL : TINGGI_NODE;
                    // kapsul dipusatkan horizontal pada kolomnya
                    const nx = x + (LEBAR_NODE - w) / 2;
                    const ny = y0 + i * (TINGGI_NODE + JEDA_Y) + (TINGGI_NODE - h) / 2;

                    const grup = el('g', {
                        class: kapsul ? 'pert-node pert-kapsul' : 'pert-node',
                        'data-id': n.id,
                        transform: `translate(${nx}, ${ny})`,
                    });
                    grup.appendChild(el('rect', {
                        x: 0, y: 0, width: w, height: h,
                        rx: kapsul ? h / 2 : 12, ry: kapsul ? h / 2 : 12,
                    }));

                    if (kapsul) {
                        grup.appendChild(el('text', {
                            x: w / 2, y: h / 2 + 4, 'text-anchor': 'middle',
                            class: 'pert-kapsul-teks',
                        }, n.nama));
                    } else {
                        grup.appendChild(el('text', { x: 14, y: 24, class: 'pert-nama' }, n.nama));
                        grup.appendChild(el('text', { x: 14, y: 42, class: 'pert-detail' },
                            'Durasi: ' + (n.durasi ?? '—') + ' hari'));
                        grup.appendChild(el('text', { x: 14, y: 58, class: 'pert-detail' },
                            'Mulai: ' + (n.start_date || '—')));
                        grup.appendChild(el('text', { x: 14, y: 72, class: 'pert-detail' },
                            'Selesai: ' + (n.end_date || '—')));
                        grup.appendChild(el('title', {},
                            n.nama + '\nDurasi: ' + (n.durasi ?? '—') + ' hari'
                            + '\nMulai: ' + (n.start_date || '—')
                            + '\nSelesai: ' + (n.end_date || '—')));
                    }

                    svg.appendChild(grup);
                });
            });
        }

        // ---------- ambil data lalu render ----------
        fetch(URL_DATA)
            .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
            .then((data) => {
                blokMuat.classList.add('d-none');
                kotak.classList.remove('d-none');
                tampilkan(data);
            })
            .catch(() => {
                const judul = blokMuat.querySelector('h5');
                const pesan = blokMuat.querySelector('p');
                if (judul) judul.textContent = 'Gagal memuat data';
                if (pesan) pesan.textContent = 'Muat ulang halaman untuk mencoba lagi.';
            });
    })();
    </script>
@endsection
