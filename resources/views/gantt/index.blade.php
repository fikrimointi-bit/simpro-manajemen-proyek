@extends('layouts.app')

@section('title', 'Gantt Chart: ' . $project->name)
@section('header-title', 'Gantt Chart')
@section('header-subtitle', $project->name)

@section('header-action')
    <a href="{{ route('projects.tasks.index', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-list-task me-1"></i> Kelola Tugas
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
            <li class="breadcrumb-item active" aria-current="page">Gantt Chart</li>
        </ol>
    </nav>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong><i class="bi bi-bar-chart-steps me-1"></i> Timeline Proyek</strong>
            <div class="btn-group" role="group" aria-label="Zoom timeline">
                <button type="button" class="btn btn-sm btn-primary" id="zoomHari" data-zoom="hari">Hari</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomMinggu" data-zoom="minggu">Minggu</button>
            </div>
        </div>

        {{-- status memuat data JSON --}}
        <div id="gantt-muat" class="empty-state">
            <i class="bi bi-hourglass-split"></i>
            <h5>Memuat jadwal…</h5>
            <p class="mb-0">Mengambil tugas, dependensi, dan kalender kerja proyek.</p>
        </div>

        {{-- kotak chart: daftar tugas (kiri) + timeline scroll (kanan) --}}
        <div id="gantt-kotak" class="gantt-kotak d-none">
            <div class="gantt-kiri">
                <div class="gantt-kepala">Daftar Tugas</div>
                <div id="gantt-daftar"></div>
            </div>
            <div class="gantt-scroll" id="gantt-scroll">
                <svg id="gantt-svg" xmlns="http://www.w3.org/2000/svg"
                     role="img" aria-label="Timeline tugas"></svg>
            </div>
        </div>

        {{-- empty state bila proyek belum punya tugas --}}
        <div id="gantt-kosong" class="empty-state d-none">
            <i class="bi bi-bar-chart"></i>
            <h5>Belum ada tugas</h5>
            <p>Tambahkan tugas dulu agar batang Gantt tampil di timeline.</p>
            <a href="{{ route('projects.tasks.create', $project) }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Tambah Tugas
            </a>
        </div>
    </div>

    {{-- legenda --}}
    <div class="card mb-3">
        <div class="card-body py-3 d-flex flex-wrap gap-4 small">
            <span><span class="gantt-legenda-swatch"></span> Weekend & hari libur</span>
            <span><span class="gantt-legenda-garis"></span> Hari ini</span>
            <span><span class="gantt-legenda-ringkas"></span> Batang ringkasan tugas induk</span>
        </div>
    </div>

    {{-- ============ Chart Gantt: SVG + JavaScript biasa, tanpa library (tahap 5A) ============ --}}
    <script>
    (function () {
        const URL_DATA = @js(route('projects.gantt.data', $project));
        const SVG_NS = 'http://www.w3.org/2000/svg';
        const TINGGI_KEPALA = 44;   // baris bulan (20px) + baris tanggal (24px)
        const TINGGI_BARIS = 30;    // tinggi satu baris tugas (sama dgn CSS)
        const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const ZOOM = { hari: 40, minggu: 10 }; // lebar piksel per hari per mode

        let lebarHari = ZOOM.hari;
        let muat = null; // isi endpoint JSON setelah fetch berhasil

        const blokMuat = document.getElementById('gantt-muat');
        const blokKosong = document.getElementById('gantt-kosong');
        const kotak = document.getElementById('gantt-kotak');
        const daftar = document.getElementById('gantt-daftar');
        const svg = document.getElementById('gantt-svg');

        // ---------- util tanggal (lokal, tanpa library) ----------
        function parseTanggal(str) {
            const [y, m, d] = str.split('-').map(Number);
            return new Date(y, m - 1, d);
        }
        function tambahHari(tgl, n) {
            const t = new Date(tgl);
            t.setDate(t.getDate() + n);
            return t;
        }
        function selisihHari(a, b) {
            return Math.round((b - a) / 86400000);
        }
        function el(tag, attr, teks) {
            const node = document.createElementNS(SVG_NS, tag);
            Object.keys(attr || {}).forEach((k) => node.setAttribute(k, attr[k]));
            if (teks !== undefined) node.textContent = teks;
            return node;
        }

        // ---------- daftar berjenjang (DFS): sub-tugas ada di bawah induknya ----------
        function susunBaris(tugas) {
            const anakPerInduk = new Map();
            const idAda = new Map(tugas.map((t) => [t.id, t]));
            const punyaAnak = new Set();

            tugas.forEach((t) => {
                if (t.parent_id === null || t.parent_id === undefined) return;
                if (idAda.has(t.parent_id)) {
                    if (!anakPerInduk.has(t.parent_id)) anakPerInduk.set(t.parent_id, []);
                    anakPerInduk.get(t.parent_id).push(t);
                    punyaAnak.add(t.parent_id);
                }
            });

            const baris = [];
            const terlihat = new Set();
            const kunjung = (t, depth) => {
                if (terlihat.has(t.id)) return;
                terlihat.add(t.id);
                baris.push({ tugas: t, depth });
                (anakPerInduk.get(t.id) || []).forEach((anak) => kunjung(anak, depth + 1));
            };
            tugas.forEach((t) => {
                if (!t.parent_id || !idAda.has(t.parent_id)) kunjung(t, 0);
            });
            tugas.forEach((t) => kunjung(t, 0)); // jaring pengaman utk induk yatim

            return { baris, punyaAnak };
        }

        // ---------- render utama ----------
        function gambar() {
            if (!muat) return;

            const data = muat;
            const { baris, punyaAnak } = susunBaris(data.tugas);
            if (baris.length === 0) {
                blokKosong.classList.remove('d-none');
                kotak.classList.add('d-none');
                return;
            }

            const weekendAktif = data.weekend_mode !== 'work';
            const setWeekend = new Set((data.weekend_days || []).map(Number));
            const hariIni = new Date();
            hariIni.setHours(0, 0, 0, 0);

            // rentang timeline: minimal dari proyek & tugas, lalu dikunci agar
            // garis "hari ini" selalu kelihatan
            let mulai = data.proyek.start_date ? parseTanggal(data.proyek.start_date) : new Date(hariIni);
            let selesai = tambahHari(mulai, 30);
            data.tugas.forEach((t) => {
                if (t.start_date && parseTanggal(t.start_date) < mulai) mulai = parseTanggal(t.start_date);
                const akhir = t.end_date ? parseTanggal(t.end_date)
                    : (t.start_date ? parseTanggal(t.start_date) : null);
                if (akhir && akhir > selesai) selesai = akhir;
            });
            if (hariIni < mulai) mulai = hariIni;
            if (hariIni > selesai) selesai = hariIni;
            mulai = tambahHari(mulai, -2);
            selesai = tambahHari(selesai, 2);

            const totalHari = selisihHari(mulai, selesai) + 1;
            const lebarTotal = totalHari * lebarHari;
            const tinggiTotal = TINGGI_KEPALA + baris.length * TINGGI_BARIS;

            // hari libur dalam rentang: berulang dicocokkan bulan + tanggal saja
            const setLibur = new Set();
            for (let i = 0; i < totalHari; i++) {
                const tgl = tambahHari(mulai, i);
                const daftarCocok = (data.hari_libur || []).filter((h) => {
                    const d = parseTanggal(h.date);
                    return h.is_recurring
                        ? d.getMonth() === tgl.getMonth() && d.getDate() === tgl.getDate()
                        : h.date === `${tgl.getFullYear()}-${String(tgl.getMonth() + 1).padStart(2, '0')}-${String(tgl.getDate()).padStart(2, '0')}`;
                });
                if (daftarCocok.length) {
                    setLibur.add(`${tgl.getFullYear()}-${String(tgl.getMonth() + 1).padStart(2, '0')}-${String(tgl.getDate()).padStart(2, '0')}`);
                }
            }

            svg.setAttribute('width', lebarTotal);
            svg.setAttribute('height', tinggiTotal);
            svg.setAttribute('viewBox', `0 0 ${lebarTotal} ${tinggiTotal}`);
            while (svg.firstChild) svg.removeChild(svg.firstChild);

            // latar dasar
            svg.appendChild(el('rect', { x: 0, y: 0, width: lebarTotal, height: tinggiTotal, class: 'gantt-bg' }));

            // kolom weekend & hari libur berlatar abu-abu (sepanjang tinggi chart)
            for (let i = 0; i < totalHari; i++) {
                const tgl = tambahHari(mulai, i);
                const kunci = `${tgl.getFullYear()}-${String(tgl.getMonth() + 1).padStart(2, '0')}-${String(tgl.getDate()).padStart(2, '0')}`;
                const akhirPekan = weekendAktif && setWeekend.has(tgl.getDay());
                if (akhirPekan || setLibur.has(kunci)) {
                    svg.appendChild(el('rect', {
                        x: i * lebarHari, y: 0,
                        width: lebarHari, height: tinggiTotal,
                        class: 'gantt-libur',
                    }));
                }
            }

            // garis kisi vertikal (per hari)
            for (let i = 1; i < totalHari; i++) {
                svg.appendChild(el('line', {
                    x1: i * lebarHari, x2: i * lebarHari,
                    y1: TINGGI_KEPALA, y2: tinggiTotal,
                    class: 'gantt-grid',
                }));
            }

            // garis pemisah antar baris tugas
            baris.forEach((b, i) => {
                const y = TINGGI_KEPALA + (i + 1) * TINGGI_BARIS;
                svg.appendChild(el('line', { x1: 0, x2: lebarTotal, y1: y, y2: y, class: 'gantt-grid' }));
            });

            // header: baris bulan + batas bulan
            let indeksBulan = 0;
            let kursor = new Date(mulai.getFullYear(), mulai.getMonth(), 1);
            while (kursor <= selesai) {
                const bulanBerikut = new Date(kursor.getFullYear(), kursor.getMonth() + 1, 1);
                const awal = kursor < mulai ? mulai : kursor;
                const x = selisihHari(mulai, awal) * lebarHari;
                const akhirSegmen = bulanBerikut > selesai ? tambahHari(selesai, 1) : bulanBerikut;
                const lebarSegmen = selisihHari(awal, akhirSegmen) * lebarHari;

                const label = NAMA_BULAN[kursor.getMonth()]
                    + (indeksBulan === 0 ? ' ' + kursor.getFullYear() : '');
                if (lebarSegmen > 26) {
                    svg.appendChild(el('text', { x: x + 6, y: 15, class: 'gantt-bulan' }, label));
                }
                if (x > 0) {
                    svg.appendChild(el('line', {
                        x1: x, x2: x, y1: 0, y2: tinggiTotal, class: 'gantt-grid',
                    }));
                }

                kursor = bulanBerikut;
                indeksBulan++;
            }

            // header: tanggal (mode hari = tiap hari; mode minggu = tiap Senin)
            for (let i = 0; i < totalHari; i++) {
                const tgl = tambahHari(mulai, i);
                if (lebarHari < 24 && tgl.getDay() !== 1) continue;
                svg.appendChild(el('text', {
                    x: i * lebarHari + lebarHari / 2,
                    y: TINGGI_KEPALA - 7,
                    class: 'gantt-teks',
                    'text-anchor': 'middle',
                }, String(tgl.getDate())));
            }

            // batas bawah header
            svg.appendChild(el('line', {
                x1: 0, x2: lebarTotal, y1: TINGGI_KEPALA, y2: TINGGI_KEPALA,
                class: 'gantt-batas-kepala',
            }));

            // batang tugas (induk = batang ringkasan lebih tipis)
            baris.forEach((baris_, i) => {
                const t = baris_.tugas;
                if (!t.start_date || !t.end_date) return;

                const x1 = selisihHari(mulai, parseTanggal(t.start_date)) * lebarHari;
                const hari = selisihHari(parseTanggal(t.start_date), parseTanggal(t.end_date)) + 1;
                const lebar = Math.max(hari * lebarHari - 4, 8);
                const induk = punyaAnak.has(t.id);
                const tinggiBatang = induk ? 10 : 16;
                const y = TINGGI_KEPALA + i * TINGGI_BARIS + (TINGGI_BARIS - tinggiBatang) / 2;

                const grup = el('g', { class: 'gantt-batang' });
                const batang = el('rect', {
                    x: x1 + 2, y: y, width: lebar, height: tinggiBatang,
                    rx: tinggiBatang / 2, ry: tinggiBatang / 2,
                });
                batang.style.fill = t.color || 'var(--simpro-primary)';
                grup.appendChild(batang);

                const orang = (t.anggota || []).map((a) => a.name).join(', ');
                grup.appendChild(el('title', {},
                    t.nama + '\n' + t.start_date + ' s/d ' + t.end_date
                    + (orang ? '\nAnggota: ' + orang : '')));
                svg.appendChild(grup);
            });

            // garis vertikal "hari ini"
            const idxHariIni = selisihHari(mulai, hariIni);
            if (idxHariIni >= 0 && idxHariIni < totalHari) {
                const x = idxHariIni * lebarHari + lebarHari / 2;
                const grup = el('g', { class: 'gantt-hari-ini' });
                grup.appendChild(el('line', { x1: x, x2: x, y1: 0, y2: tinggiTotal }));
                grup.appendChild(el('text', { x: x + 5, y: 13, class: 'gantt-label-hari-ini' }, 'Hari ini'));
                svg.appendChild(grup);
            }

            // daftar tugas di kiri, sejajar baris timeline
            daftar.textContent = '';
            baris.forEach((baris_) => {
                const t = baris_.tugas;
                const barisEl = document.createElement('div');
                barisEl.className = 'gantt-baris' + (punyaAnak.has(t.id) ? ' gantt-induk' : '');
                barisEl.style.paddingLeft = (14 + baris_.depth * 16) + 'px';

                const dot = document.createElement('span');
                dot.className = 'warna-dot gantt-dot';
                dot.style.backgroundColor = t.color || 'var(--simpro-muted)';

                const nama = document.createElement('span');
                nama.className = 'gantt-nama';
                nama.textContent = t.nama;

                barisEl.appendChild(dot);
                barisEl.appendChild(nama);

                const orang = (t.anggota || []).map((a) => a.name).join(', ');
                if (orang) {
                    const catatan = document.createElement('span');
                    catatan.className = 'gantt-anggota text-muted-simpro';
                    catatan.textContent = orang;
                    barisEl.appendChild(catatan);
                    barisEl.title = t.nama + ' — ' + orang;
                }
                daftar.appendChild(barisEl);
            });
        }

        // ---------- zoom hari / minggu ----------
        function setZoom(mode) {
            lebarHari = ZOOM[mode] || ZOOM.hari;
            document.querySelectorAll('[data-zoom]').forEach((tombol) => {
                const aktif = tombol.dataset.zoom === mode;
                tombol.classList.toggle('btn-primary', aktif);
                tombol.classList.toggle('btn-outline-secondary', !aktif);
            });
            gambar();
        }

        document.querySelectorAll('[data-zoom]').forEach((tombol) => {
            tombol.addEventListener('click', () => setZoom(tombol.dataset.zoom));
        });

        // ---------- ambil data lalu render ----------
        fetch(URL_DATA)
            .then((res) => (res.ok ? res.json() : Promise.reject(res.status)))
            .then((data) => {
                muat = data;
                blokMuat.classList.add('d-none');
                if (!data.tugas.length) {
                    blokKosong.classList.remove('d-none');
                } else {
                    kotak.classList.remove('d-none');
                    setZoom('hari');
                }
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