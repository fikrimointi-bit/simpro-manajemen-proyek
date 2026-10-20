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
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger"
                        id="pert-kritis" aria-pressed="false" title="Sorot jalur kritis">
                    <i class="bi bi-exclamation-octagon me-1"></i> Tampilkan jalur kritis
                </button>
                <div class="btn-group" role="group" aria-label="Kontrol tampilan diagram">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            id="pert-zoom-out" aria-label="Perkecil diagram" title="Perkecil">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            id="pert-zoom-in" aria-label="Perbesar diagram" title="Perbesar">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            id="pert-reset" title="Kembalikan tampilan seperti awal">
                        <i class="bi bi-arrows-fullscreen me-1"></i> Reset tampilan
                    </button>
                </div>
                <a href="{{ route('projects.pert.data', $project) }}" class="btn btn-sm btn-outline-secondary"
                   target="_blank" rel="noopener">
                    <i class="bi bi-code-slash me-1"></i> Lihat JSON
                </a>
            </div>
        </div>

        {{-- status memuat data JSON --}}
        <div id="pert-muat" class="empty-state">
            <i class="bi bi-hourglass-split"></i>
            <h5>Memuat diagram…</h5>
            <p class="mb-0">Mengambil node dan layer dari data PERT proyek.</p>
        </div>

        {{-- kotak diagram: node per layer, di-scroll bila lebar --}}
        <div id="pert-kotak" class="d-none">
            <div class="pert-scroll" id="pert-scroll">
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
        const wadah = document.getElementById('pert-scroll');

        // ---------- kontrol tampilan: drag geser + zoom scroll/tombol (tahap 6C) ----------
        // viewBox = posisi (x,y) + skala k dalam satuan koordinat diagram,
        // jadi kotak & panah yang sudah digambar tidak perlu diubah sama sekali
        const tampil = { x: 0, y: 0, k: 1 };
        const dunia = { w: 0, h: 0 }; // ukuran konten dalam satuan diagram

        function viewport() {
            const r = svg.getBoundingClientRect();
            return { vw: Math.max(r.width, 1), vh: Math.max(r.height, 1) };
        }

        function terapkan() {
            if (!dunia.w) return;
            const { vw, vh } = viewport();
            svg.setAttribute('viewBox',
                `${tampil.x} ${tampil.y} ${vw / tampil.k} ${vh / tampil.k}`);
        }

        // batasi geser: saat zoom masuk (viewport lebih kecil dari diagram) geseran
        // dibatasi tepi konten; saat diagram muat di layar tetap boleh digeser
        // sedikit dalam ruang longgar 25% viewport (bukan dikunci pas di tengah)
        function batasi() {
            const { vw, vh } = viewport();
            const lebar = vw / tampil.k;
            const tinggi = vh / tampil.k;
            const longgar = Math.max(vw, vh) * 0.25;

            if (lebar >= dunia.w) {
                const pusat = (dunia.w - lebar) / 2;
                tampil.x = Math.min(pusat + longgar, Math.max(pusat - longgar, tampil.x));
            } else {
                tampil.x = Math.min(0, Math.max(dunia.w - lebar, tampil.x));
            }

            if (tinggi >= dunia.h) {
                const pusat = (dunia.h - tinggi) / 2;
                tampil.y = Math.min(pusat + longgar, Math.max(pusat - longgar, tampil.y));
            } else {
                tampil.y = Math.min(0, Math.max(dunia.h - tinggi, tampil.y));
            }
        }

        // muat di layar: skala maksimal yang memuat seluruh diagram (maks 1x)
        function muatLayar() {
            if (!dunia.w) return;
            const { vw, vh } = viewport();
            tampil.k = Math.min(1, (vw * 0.96) / dunia.w, (vh * 0.96) / dunia.h);
            tampil.x = (dunia.w - vw / tampil.k) / 2;
            tampil.y = (dunia.h - vh / tampil.k) / 2;
            terapkan();
        }

        // zoom di titik piksel (px,py) di dalam svg — pusat titik di bawah kursor
        function zoom(px, py, faktor) {
            if (!dunia.w) return;
            const wx = tampil.x + px / tampil.k;
            const wy = tampil.y + py / tampil.k;
            tampil.k = Math.min(4, Math.max(0.2, tampil.k * faktor));
            tampil.x = wx - px / tampil.k;
            tampil.y = wy - py / tampil.k;
            batasi();
            terapkan();
        }

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

            // id tugas kritis + pasangan edge antar tugas kritis (tahap 7B-2)
            const setKritis = new Set((data.tugas_kritis || []).map(String));
            const setEdgeKritis = new Set(
                (data.edge_kritis || []).map((e) => String(e.dari) + '-' + String(e.ke)));

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

            // posisi tiap node dihitung dulu (tanpa menggambar) agar panah bisa
            // ditarik sebelum kotak — urutan SVG: panah dulu, kotak sesudahnya
            const posisi = new Map(); // id → { kiri, kanan, yTengah }
            layers.forEach((l, kolom) => {
                const isi = perLayer.get(l);
                const x = PADDING + kolom * (LEBAR_NODE + JEDA_X);
                const y0 = PADDING + (tinggiTotal - PADDING * 2 - tinggiKolom(l)) / 2;

                isi.forEach((n, i) => {
                    const kapsul = n.id === 'mulai' || n.id === 'selesai';
                    const w = kapsul ? LEBAR_KAPSUL : LEBAR_NODE;
                    const h = kapsul ? TINGGI_KAPSUL : TINGGI_NODE;
                    const nx = x + (LEBAR_NODE - w) / 2;
                    const ny = y0 + i * (TINGGI_NODE + JEDA_Y) + (TINGGI_NODE - h) / 2;
                    posisi.set(String(n.id), {
                        kiri: nx, kanan: nx + w, yTengah: ny + h / 2,
                    });
                });
            });

            // kepala panah untuk seluruh edge
            const defs = el('defs', {});
            const kepalaPanah = el('marker', {
                id: 'pert-panah-kepala',
                markerUnits: 'userSpaceOnUse',
                markerWidth: 7,
                markerHeight: 7,
                refX: 6,
                refY: 3,
                orient: 'auto',
            });
            kepalaPanah.appendChild(el('path', { d: 'M0,0 L6,3 L0,6 Z', class: 'pert-panah-kepala' }));
            defs.appendChild(kepalaPanah);

            // marker khusus utk edge jalur kritis (dipilih via marker-end saat mode aktif)
            const kepalaKritis = el('marker', {
                id: 'pert-panah-kepala-kritis',
                markerUnits: 'userSpaceOnUse',
                markerWidth: 7,
                markerHeight: 7,
                refX: 6,
                refY: 3,
                orient: 'auto',
            });
            kepalaKritis.appendChild(el('path', { d: 'M0,0 L6,3 L0,6 Z', class: 'pert-panah-kepala-kritis' }));
            defs.appendChild(kepalaKritis);
            svg.appendChild(defs);

            // panah siku: tepi kanan node asal → tepi kiri node tujuan
            // (digambar sebelum kotak agar tidak memotong kotak mana pun)
            (data.edge || []).forEach((e) => {
                const p = posisi.get(String(e.dari));
                const s = posisi.get(String(e.ke));
                if (!p || !s) return; // ujung tidak ada di diagram → tanpa panah

                const xP = p.kanan;
                const yP = p.yTengah;
                const xS = s.kiri;
                const yS = s.yTengah;
                const siku = Math.round((xP + xS) / 2); // titik belok di tengah kolom

                const idEdge = String(e.dari) + '-' + String(e.ke);
                const edgeKritis = setEdgeKritis.has(idEdge);

                const grup = el('g', {
                    class: 'pert-edge' + (edgeKritis ? ' pert-edge-kritis' : ''),
                    'data-id': e.dari + '-' + e.ke,
                });
                grup.appendChild(el('path', {
                    d: 'M ' + xP + ' ' + yP + ' H ' + siku + ' V ' + yS + ' H ' + xS,
                    class: 'pert-panah',
                    'marker-end': edgeKritis
                        ? 'url(#pert-panah-kepala-kritis)'
                        : 'url(#pert-panah-kepala)',
                }));

                // label kecil tipe+delay (mis. "SS+1") hanya untuk edge selain FS
                const tipe = String(e.tipe || 'FS').toUpperCase();
                const delay = Number(e.delay) || 0;
                if (tipe !== 'FS') {
                    grup.appendChild(el('text', {
                        x: siku + 4,
                        y: (yP + yS) / 2 - 4,
                        class: 'pert-panah-label',
                    }, tipe + (delay > 0 ? '+' + delay : delay < 0 ? String(delay) : '')));
                }

                svg.appendChild(grup);
            });

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
                        class: (kapsul ? 'pert-node pert-kapsul' : 'pert-node')
                            + (setKritis.has(String(n.id)) ? ' pert-node-kritis' : ''),
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

            // ukuran konten diketahui → tampilan awal muat di layar
            dunia.w = lebarTotal;
            dunia.h = tinggiTotal;
            muatLayar();
        }

        // ---------- drag geser (mouse / sentuh) ----------
        // dipasang di WADAH (mencakup area kosong di luar svg juga) + window
        // untuk move/up, sehingga geser tetap jalan walau kursor keluar wadah
        let geser = null;
        wadah.addEventListener('pointerdown', (e) => {
            if (!dunia.w || (e.button !== undefined && e.button !== 0)) return;
            e.preventDefault(); // cegah seleksi teks / gestur bawaan browser
            geser = { x: e.clientX, y: e.clientY };
            wadah.setPointerCapture(e.pointerId);
            wadah.classList.add('pert-menggeser');
        });
        window.addEventListener('pointermove', (e) => {
            if (!geser) return;
            tampil.x -= (e.clientX - geser.x) / tampil.k;
            tampil.y -= (e.clientY - geser.y) / tampil.k;
            geser = { x: e.clientX, y: e.clientY };
            batasi();
            terapkan();
        });
        const berhentiGeser = (e) => {
            if (!geser) return;
            geser = null;
            wadah.classList.remove('pert-menggeser');
            if (e.pointerId !== undefined && wadah.hasPointerCapture(e.pointerId)) {
                wadah.releasePointerCapture(e.pointerId);
            }
        };
        window.addEventListener('pointerup', berhentiGeser);
        window.addEventListener('pointercancel', berhentiGeser);

        // ---------- zoom scroll mouse (pusat di bawah kursor) ----------
        svg.addEventListener('wheel', (e) => {
            if (!dunia.w) return;
            e.preventDefault();
            const r = svg.getBoundingClientRect();
            zoom(e.clientX - r.left, e.clientY - r.top, e.deltaY < 0 ? 1.15 : 1 / 1.15);
        }, { passive: false });

        // ---------- tombol + / − (zoom di tengah layar) & reset ----------
        document.getElementById('pert-zoom-in').addEventListener('click', () => {
            const { vw, vh } = viewport();
            zoom(vw / 2, vh / 2, 1.25);
        });
        document.getElementById('pert-zoom-out').addEventListener('click', () => {
            const { vw, vh } = viewport();
            zoom(vw / 2, vh / 2, 1 / 1.25);
        });
        document.getElementById('pert-reset').addEventListener('click', muatLayar);

        // ---------- toggle mode jalur kritis: sorot kotak & panah kritis merah ----------
        // kelas 'kritis' sudah menempel di node/edge saat render;
        // toggle hanya menyalakan kelas mode pada SVG (posisi tidak berubah)
        document.getElementById('pert-kritis').addEventListener('click', function () {
            const aktif = svg.classList.toggle('pert-mode-kritis');
            this.classList.toggle('btn-danger', aktif);
            this.classList.toggle('btn-outline-danger', !aktif);
            this.setAttribute('aria-pressed', String(aktif));
        });

        // viewport berubah (resize jendela) → viewBox disesuaikan, diagram tetap pas
        window.addEventListener('resize', () => {
            batasi();
            terapkan();
        });

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
