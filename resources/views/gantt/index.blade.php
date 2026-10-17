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

        {{-- kotak chart: daftar tugas (kiri) + header tanggal sticky & timeline scroll (kanan) --}}
        <div id="gantt-kotak" class="gantt-kotak d-none">
            <div class="gantt-kiri">
                <div class="gantt-kepala">Daftar Tugas</div>
                <div id="gantt-daftar"></div>
            </div>
            <div class="gantt-kanan">
                <div class="gantt-kepala gantt-kepala-kanan" id="gantt-kepala-kanan">
                    <svg id="gantt-kepala-svg" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"></svg>
                </div>
                <div class="gantt-scroll" id="gantt-scroll">
                    <svg id="gantt-svg" xmlns="http://www.w3.org/2000/svg"
                         role="img" aria-label="Timeline tugas"></svg>
                </div>
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

    {{-- ============ Chart Gantt: SVG + JavaScript biasa, tanpa library (tahap 5A/5B) ============ --}}
    <script>
    (function () {
        const URL_DATA = @js(route('projects.gantt.data', $project));
        const SVG_NS = 'http://www.w3.org/2000/svg';
        const TINGGI_KEPALA = 44;   // baris bulan (20px) + baris tanggal (24px) — SVG header terpisah
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
        const kepalaKanan = document.getElementById('gantt-kepala-kanan');
        const kepalaSvg = document.getElementById('gantt-kepala-svg');
        const ganttScroll = document.getElementById('gantt-scroll');

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
        function potong(teks, maks) {
            return teks.length > maks ? teks.slice(0, Math.max(maks - 1, 0)) + '…' : teks;
        }
        function kunciTanggal(tgl) {
            return `${tgl.getFullYear()}-${String(tgl.getMonth() + 1).padStart(2, '0')}-${String(tgl.getDate()).padStart(2, '0')}`;
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

        // ---------- header bulan + tanggal: SVG terpisah agar menempel saat scroll vertikal ----------
        function gambarKepala(o) {
            kepalaSvg.setAttribute('width', o.lebarTotal);
            kepalaSvg.setAttribute('height', TINGGI_KEPALA);
            kepalaSvg.setAttribute('viewBox', `0 0 ${o.lebarTotal} ${TINGGI_KEPALA}`);
            while (kepalaSvg.firstChild) kepalaSvg.removeChild(kepalaSvg.firstChild);

            kepalaSvg.appendChild(el('rect', {
                x: 0, y: 0, width: o.lebarTotal, height: TINGGI_KEPALA, class: 'gantt-bg',
            }));

            // kolom weekend & hari libur (lanjutan kolom di body agar sejajar)
            for (let i = 0; i < o.totalHari; i++) {
                const tgl = tambahHari(o.mulai, i);
                const akhirPekan = o.weekendAktif && o.setWeekend.has(tgl.getDay());
                if (akhirPekan || o.setLibur.has(kunciTanggal(tgl))) {
                    kepalaSvg.appendChild(el('rect', {
                        x: i * lebarHari, y: 0,
                        width: lebarHari, height: TINGGI_KEPALA,
                        class: 'gantt-libur',
                    }));
                }
            }

            // garis kisi vertikal (per hari)
            for (let i = 1; i < o.totalHari; i++) {
                kepalaSvg.appendChild(el('line', {
                    x1: i * lebarHari, x2: i * lebarHari,
                    y1: 0, y2: TINGGI_KEPALA, class: 'gantt-grid',
                }));
            }

            // baris bulan: label + batas segmen; kotak teks disimpan utk cek label "Hari ini"
            const kotakBulan = [];
            let indeksBulan = 0;
            let kursor = new Date(o.mulai.getFullYear(), o.mulai.getMonth(), 1);
            while (kursor <= o.selesai) {
                const bulanBerikut = new Date(kursor.getFullYear(), kursor.getMonth() + 1, 1);
                const awal = kursor < o.mulai ? o.mulai : kursor;
                const x = selisihHari(o.mulai, awal) * lebarHari;
                const akhirSegmen = bulanBerikut > o.selesai ? tambahHari(o.selesai, 1) : bulanBerikut;
                const lebarSegmen = selisihHari(awal, akhirSegmen) * lebarHari;

                const label = NAMA_BULAN[kursor.getMonth()]
                    + (indeksBulan === 0 ? ' ' + kursor.getFullYear() : '');
                if (lebarSegmen > 26) {
                    kepalaSvg.appendChild(el('text', { x: x + 6, y: 15, class: 'gantt-bulan' }, label));
                    // perkiraan lebar teks bulan ~7px/huruf @ font 11px semi-bold
                    kotakBulan.push({ kiri: x + 6, kanan: x + 6 + label.length * 7 });
                }
                if (x > 0) {
                    kepalaSvg.appendChild(el('line', {
                        x1: x, x2: x, y1: 0, y2: TINGGI_KEPALA, class: 'gantt-grid',
                    }));
                }

                kursor = bulanBerikut;
                indeksBulan++;
            }

            // baris tanggal (mode hari = tiap hari; mode minggu = tiap Senin)
            for (let i = 0; i < o.totalHari; i++) {
                const tgl = tambahHari(o.mulai, i);
                if (lebarHari < 24 && tgl.getDay() !== 1) continue;
                kepalaSvg.appendChild(el('text', {
                    x: i * lebarHari + lebarHari / 2,
                    y: TINGGI_KEPALA - 7,
                    class: 'gantt-teks',
                    'text-anchor': 'middle',
                }, String(tgl.getDate())));
            }

            // garis merah "hari ini" — labelnya digeser agar tidak menimpa teks lain:
            // coba dulu di kanan garis, bila berhimpitan dgn label bulan coba di kiri garis,
            // bila tetap berhimpitan / keluar tepi, label disembunyikan (garis tetap tampil)
            const idxHariIni = selisihHari(o.mulai, o.hariIni);
            if (idxHariIni >= 0 && idxHariIni < o.totalHari) {
                const x = idxHariIni * lebarHari + lebarHari / 2;
                const grup = el('g', { class: 'gantt-hari-ini' });
                grup.appendChild(el('line', { x1: x, x2: x, y1: 0, y2: TINGGI_KEPALA }));

                const LEBAR_LABEL = 'Hari ini'.length * 6; // ~48px @ font 10px
                const JEDA = 6;                            // jarak aman antar teks
                const berhimpitan = (kiri, kanan) => kotakBulan.some(
                    (b) => kiri - JEDA < b.kanan && kanan + JEDA > b.kiri
                );
                const kandidat = [
                    { x: x + 5, anchor: 'start', kiri: x + 5, kanan: x + 5 + LEBAR_LABEL },
                    { x: x - 5, anchor: 'end', kiri: x - 5 - LEBAR_LABEL, kanan: x - 5 },
                ];
                const cocok = kandidat.find((k) =>
                    k.kiri >= 2 && k.kanan <= o.lebarTotal - 2 && !berhimpitan(k.kiri, k.kanan)
                );
                if (cocok) {
                    grup.appendChild(el('text', {
                        x: cocok.x,
                        y: 13,
                        class: 'gantt-label-hari-ini',
                        'text-anchor': cocok.anchor,
                    }, 'Hari ini'));
                }
                kepalaSvg.appendChild(grup);
            }

            // selalu sejajar dgn posisi scroll horizontal timeline
            kepalaKanan.scrollLeft = ganttScroll.scrollLeft;
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
            const tinggiTotal = baris.length * TINGGI_BARIS; // baris saja — header di SVG terpisah

            // hari libur dalam rentang: berulang dicocokkan bulan + tanggal saja
            const setLibur = new Set();
            for (let i = 0; i < totalHari; i++) {
                const tgl = tambahHari(mulai, i);
                const daftarCocok = (data.hari_libur || []).filter((h) => {
                    const d = parseTanggal(h.date);
                    return h.is_recurring
                        ? d.getMonth() === tgl.getMonth() && d.getDate() === tgl.getDate()
                        : h.date === kunciTanggal(tgl);
                });
                if (daftarCocok.length) {
                    setLibur.add(kunciTanggal(tgl));
                }
            }

            svg.setAttribute('width', lebarTotal);
            svg.setAttribute('height', tinggiTotal);
            svg.setAttribute('viewBox', `0 0 ${lebarTotal} ${tinggiTotal}`);
            while (svg.firstChild) svg.removeChild(svg.firstChild);

            // kepala panah untuk garis dependensi
            const defs = el('defs', {});
            const kepalaPanah = el('marker', {
                id: 'gantt-panah-kepala',
                markerUnits: 'userSpaceOnUse',
                markerWidth: 7,
                markerHeight: 7,
                refX: 6,
                refY: 3,
                orient: 'auto',
            });
            kepalaPanah.appendChild(el('path', { d: 'M0,0 L6,3 L0,6 Z', class: 'gantt-panah-kepala' }));
            defs.appendChild(kepalaPanah);
            svg.appendChild(defs);

            // latar dasar
            svg.appendChild(el('rect', { x: 0, y: 0, width: lebarTotal, height: tinggiTotal, class: 'gantt-bg' }));

            // kolom weekend & hari libur berlatar abu-abu (sepanjang tinggi chart)
            for (let i = 0; i < totalHari; i++) {
                const tgl = tambahHari(mulai, i);
                const akhirPekan = weekendAktif && setWeekend.has(tgl.getDay());
                if (akhirPekan || setLibur.has(kunciTanggal(tgl))) {
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
                    y1: 0, y2: tinggiTotal,
                    class: 'gantt-grid',
                }));
            }

            // garis pemisah antar baris tugas
            baris.forEach((b, i) => {
                const y = (i + 1) * TINGGI_BARIS;
                svg.appendChild(el('line', { x1: 0, x2: lebarTotal, y1: y, y2: y, class: 'gantt-grid' }));
            });

            // batang tugas (induk = batang ringkasan lebih tipis)
            const posisiBatang = new Map(); // id → ujung kiri/kanan + y tengah baris
            const labelSiap = [];           // label anggota, digambar setelah panah dependensi
            baris.forEach((baris_, i) => {
                const t = baris_.tugas;
                if (!t.start_date || !t.end_date) return;

                const x1 = selisihHari(mulai, parseTanggal(t.start_date)) * lebarHari;
                const hari = selisihHari(parseTanggal(t.start_date), parseTanggal(t.end_date)) + 1;
                const lebar = Math.max(hari * lebarHari - 4, 8);
                const induk = punyaAnak.has(t.id);
                const tinggiBatang = induk ? 10 : 16;
                const y = i * TINGGI_BARIS + (TINGGI_BARIS - tinggiBatang) / 2;

                const grup = el('g', { class: 'gantt-batang' });
                const batang = el('rect', {
                    x: x1 + 2, y: y, width: lebar, height: tinggiBatang,
                    rx: tinggiBatang / 2, ry: tinggiBatang / 2,
                });
                batang.style.fill = t.color || 'var(--simpro-primary)';
                grup.appendChild(batang);

                posisiBatang.set(t.id, {
                    kiri: x1 + 2,
                    kanan: x1 + 2 + lebar,
                    y: i * TINGGI_BARIS + TINGGI_BARIS / 2,
                });

                // nama anggota singkat di samping kanan batang — tugas induk tanpa label,
                // dipotong agar tidak melewati tepi timeline; digambar setelah panah dependensi
                // dgn latar tipis supaya tidak tertimpa garis panah
                const daftarAnggota = t.anggota || [];
                if (!induk && daftarAnggota.length) {
                    const depan = (nama) => String(nama).trim().split(/\s+/)[0];
                    const singkat = daftarAnggota.length > 2
                        ? depan(daftarAnggota[0].name) + ' +' + (daftarAnggota.length - 1)
                        : daftarAnggota.map((a) => depan(a.name)).join(', ');
                    const xLabel = x1 + 2 + lebar + 6;
                    const ruang = lebarTotal - xLabel - 4; // sisa ruang s/d tepi timeline
                    const maksHuruf = Math.floor(ruang / 6); // lebar huruf rata2 ~6px @10px
                    if (ruang >= 12 && maksHuruf >= 1) {
                        labelSiap.push({
                            x: xLabel,
                            y: y + tinggiBatang / 2 + 3.5,
                            teks: potong(singkat, maksHuruf),
                        });
                    }
                }

                const orang = (t.anggota || []).map((a) => a.name).join(', ');
                grup.appendChild(el('title', {},
                    t.nama + '\n' + t.start_date + ' s/d ' + t.end_date
                    + (orang ? '\nAnggota: ' + orang : '')));
                svg.appendChild(grup);
            });

            // panah dependensi siku antar batang yang hanya terlihat di timeline:
            // FS: ujung kanan pendahulu → ujung kiri tugas | SS: kiri → kiri
            // FF: kanan → kanan                            | SF: kiri pendahulu → kanan tugas
            (data.dependensi || []).forEach((dep) => {
                if (dep.predecessor_id === dep.task_id) return; // abaikan panah ke diri sendiri

                const p = posisiBatang.get(dep.predecessor_id);
                const s = posisiBatang.get(dep.task_id);
                if (!p || !s) return; // tanpa batang (tak tampil) → tanpa panah

                const tipe = String(dep.tipe || 'FS').toUpperCase();
                const xP = (tipe === 'SS' || tipe === 'SF') ? p.kiri : p.kanan;
                const xS = (tipe === 'FS' || tipe === 'SS') ? s.kiri : s.kanan;
                const siku = Math.round((xP + xS) / 2); // titik belok di tengah

                svg.appendChild(el('path', {
                    d: 'M ' + xP + ' ' + p.y + ' H ' + siku + ' V ' + s.y + ' H ' + xS,
                    class: 'gantt-panah',
                    'marker-end': 'url(#gantt-panah-kepala)',
                }));
            });

            // label nama anggota: di atas panah dependensi, dgn latar tipis agar
            // garis panah tidak menimpa teks
            labelSiap.forEach((lb) => {
                svg.appendChild(el('rect', {
                    x: lb.x - 3,
                    y: lb.y - 9,
                    width: lb.teks.length * 6 + 6,
                    height: 12,
                    rx: 3, ry: 3,
                    class: 'gantt-label-anggota-latar',
                }));
                svg.appendChild(el('text', {
                    x: lb.x, y: lb.y, class: 'gantt-label-anggota',
                }, lb.teks));
            });

            // garis vertikal "hari ini" pada body (labelnya ada di header, digambar
            // oleh gambarKepala agar tidak menimpa teks bulan/tanggal)
            const idxHariIni = selisihHari(mulai, hariIni);
            if (idxHariIni >= 0 && idxHariIni < totalHari) {
                const x = idxHariIni * lebarHari + lebarHari / 2;
                const grup = el('g', { class: 'gantt-hari-ini' });
                grup.appendChild(el('line', { x1: x, x2: x, y1: 0, y2: tinggiTotal }));
                svg.appendChild(grup);
            }

            // header bulan + tanggal (SVG terpisah, menempel saat scroll vertikal)
            gambarKepala({
                lebarTotal, mulai, selesai, totalHari,
                weekendAktif, setWeekend, setLibur, hariIni,
            });

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

        // header tanggal ikut bergeser saat timeline digulir horizontal
        ganttScroll.addEventListener('scroll', () => {
            kepalaKanan.scrollLeft = ganttScroll.scrollLeft;
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