# knowledge.md — Manajemen Proyek ala GanttProject

## Tujuan

Website manajemen proyek sederhana untuk tugas kuliah, terinspirasi GanttProject:
membuat proyek, mengatur hari kerja (weekend + hari libur), dan ke depannya
menampilkan tugas, dependensi, serta alokasi sumber daya pada chart Gantt.

## Stack & Konvensi

- Laravel (versi terpasang di folder ini, `laravel/framework ^13`), Blade, SQLite.
- CSS biasa + Bootstrap 5 via CDN, tanpa build step Vite (layout memakai `asset('css/app.css')`).
- Tanpa autentikasi; semua orang bisa mengelola proyek.
- Antarmuka bahasa Indonesia; komentar kode singkat berbahasa Indonesia.
- Dijalankan di Laragon (Windows) dengan PHP 8.3.

## Keputusan Desain

### Skema database (7 tabel, dibuat sekaligus meski UI bertahap)

| Tabel | Peran |
| --- | --- |
| `projects` | Induk. Menyimpan `weekend_days` (JSON, angka 0–6, 0 = Minggu) dan `weekend_mode` (`no_work` / `work`). |
| `holidays` | Hari libur proyek; `is_recurring = true` berarti berulang tiap tahun (hanya bulan+tanggal yang berarti). Unique `(project_id, date)` mencegah duplikat. |
| `tasks` | Tugas dengan `parent_id` (nullOnDelete) untuk struktur WBS/sub-tugas, plus progress, milestone, dan urutan. |
| `task_dependencies` | Ketergantungan antar tugas ala GanttProject: `predecessor_id` (pendahulu) → `successor_id` (tugas yang **menunggu**), tipe FS/SS/FF/SF, dan `lag_days` (= **delay**, hari kerja). Nama kolom lama dipertahankan — migration `000004` sudah lengkap, tidak ada migration tambahan. |
| `resources` | Anggota tim dengan kapasitas (persen) dan warna untuk chart. |
| `resource_days_off` | Cuti/sakit per anggota; perilaku rekurensi sama dengan `holidays`. |
| `resource_task` | Pivot alokasi anggota → tugas dengan `effort` (jam/hari). |

Semua tabel turunan memakai `cascadeOnDelete` ke induknya supaya penghapusan
proyek otomatis membersihkan data turunan. UI untuk `tasks`, `resources`,
`resource_days_off`, dan `resource_task` dibuat bertahap (tahap berikutnya).

### Kalender kerja: `App\Services\CalendarService`

Layanan murni logika (tanpa akses request/session/view):

- `isWorkingDay(Project, date)` — urutan cek: (1) hari libur proyek, (2) weekend
  dengan aturan `weekend_mode`. `work` = weekend tetap hari kerja.
- `addWorkingDays(Project, date, n)` — geser N hari kerja; tanggal awal tidak
  dihitung; `n` boleh negatif (geser ke belakang).
- `nextWorkingDay(Project, date)` — tanggal itu sendiri bila sudah hari kerja,
  selain itu digeser maju sampai hari kerja.
- `countWorkingDays(Project, start, end)` — jumlah hari kerja inklusif dua ujung;
  rentang terbalik menghasilkan 0.
- Libur berulang dicocokkan lewat bulan + tanggal saja; lookup libur di-cache
  per proyek di memori supaya loop harian tidak query berulang.

### Hari libur: rekurensi

Baris `holidays` dengan `is_recurring = true` disimpan dengan tanggal lengkap
(tahun pembuatan tidak berarti) — pencocokan hanya memakai bulan + tanggal.
Ini meniru cara GanttProject menyimpan libur tahunan tanpa perlu menyimpan
ribuan baris.

### Bahasa antarmuka

- `AppServiceProvider::boot()` memaksa `app()->setLocale('id')` sehingga pesan
  validasi otomatis berbahasa Indonesia memakai `lang/id/validation.php`,
  tanpa perlu mengubah `.env`.
- Format tanggal tampilan memakai helper `App\Support\Tanggal` (independen
  terhadap locale Carbon) supaya selalu "Senin, 14 Oktober 2026".

### Route

- `resource('projects')` untuk CRUD proyek.
- `PATCH /projects/{project}/weekend` untuk pengaturan weekend di halaman detail.
- `POST /projects/{project}/holidays` dan `DELETE .../holidays/{holiday}` untuk
  kelola hari libur.
- `resource('projects.tasks')` (tanpa `show`) untuk CRUD tugas bersarang.

### CRUD tugas (tahap 2)

- **Field**: nama, mulai, durasi (hari kerja), prioritas (rendah/normal/tinggi),
  warna, `parent_id`. Kolom `priority` + tanggal nullable + FK `parent_id`
  cascadeOnDelete ditambahkan migration `2026_10_14_000008_upgrade_tasks_table_for_crud.php`.
- **Tanggal selesai tugas anak**: `end_date = addWorkingDays(start, durasi - 1)`
  memakai CalendarService, jadi otomatis melewati weekend & hari libur proyek.
- **Tiga jenis tugas** dibedakan dari isian form, bukan dari tipe kolom:
  (1) tugas **anak** (pilih induk, jadwal wajib), (2) tugas **mandiri** (tanpa
  induk, jadwal diisi → dihitung seperti anak), (3) tugas **induk** (jadwal
  dikosongkan saat dibuat, menyusul dari sub-tugas). Validasi memakai
  `required_with` agar jadwal selalu lengkap berduaan.
- **Tugas induk** tidak mengisi tanggal/durasi; `Task::recomputeSchedule()`
  mengagregasi: mulai = min tanggal anak, selesai = max tanggal anak, durasi =
  `countWorkingDays` rentang tersebut. Rollup berjalan naik ke puncak pohon setiap
  ada perubahan anak (memakai `updateQuietly`, tanpa event loop).
- **Cegah loop induk**: divalidasi di `UpdateTaskRequest::withValidator()`
  (dilarang jadi induk dirinya sendiri/turunannya, memakai `Task::descendantIds()`);
  form edit juga menyembunyikan kandidat turunan dari dropdown induk.
- **Hapus induk**: seluruh subtree dihapus (query massal di controller + FK cascade
  sebagai jaring pengaman), dengan konfirmasi di UI.
- **Validasi memakai Form Request**: `StoreTaskRequest` (aturan kondisional: anak
  wajib tanggal & durasi, induk tidak) dan `UpdateTaskRequest` (turunan + cegah siklus).
- **Tampilan**: tabel berjenjang dengan indentasi per level (DFS dari akar), badge
  jumlah sub-tugas, dot warna, dan badge prioritas.

### Mesin penjadwalan dependensi (tahap 3A): `App\Services\SchedulerService`

`scheduleProject(Project)` menjadwalkan ulang seluruh proyek dalam 4 fase:
urutan topologis → hitung seluruh jadwal leaf (murni perhitungan, tanpa
menulis) → tulis tanggal yang berubah (`updateQuietly`) → agregasi induk.
Sehingga bila terjadi kegagalan, tidak ada satu pun tanggal yang tersimpan.

Aturan (semua perhitungan hari kerja lewat CalendarService, tanggal inklusif):

| Tipe | Aturan |
| --- | --- |
| FS | mulai di hari kerja **setelah** pendahulu selesai + delay |
| SS | mulai = mulai pendahulu + delay |
| FF | selesai = selesai pendahulu + delay, mulai dihitung mundur (durasi − 1) |
| SF | selesai = mulai pendahulu + delay, mulai dihitung mundur (durasi − 1) |

- Banyak predecessor → hitung tiap kandidat, ambil yang **paling lambat**.
- Tanpa predecessor → pakai `start_date` tugas itu sendiri (tidak ada snapping).
- `delay` = kolom `lag_days`; `successor_id` = tugas yang menunggu. Nama kolom
  lama TIDAK direname (keputusan tahap 3A), jadi tidak ada migration baru.
- **Dependensi hanya antar tugas leaf** (tanpa sub-tugas); dependensi yang
  menyentuh tugas induk diabaikan penjadwal. Induk TIDAK pernah dijadwalkan
  lewat dependensi — induk diagregasi dari sub-tugas (`Task::recomputeSchedule`)
  **setelah** seluruh leaf selesai, diproses dari kedalaman terbesar ke atas.
- Urutan pengerjaan memakai **topological sort** (algoritma Kahn). Siklus
  (A → B → A) memicu `App\Exceptions\SchedulingException` berpesan Indonesia
  yang dilempar **sebelum** tulisan apa pun → perubahan tidak tersimpan.
- Pemicu jadwal ulang:
  - `TaskController@store/update/destroy` (tugas berubah);
  - event model `TaskDependency`: `saved`/`deleted` (dependensi berubah), dan
    `saving` → `SchedulerService::assertNoCycle()` menolak siklus **sebelum**
    baris masuk database;
  - `HolidayController@store/destroy` + `ProjectController@update/updateWeekend`
    (kalender berubah).
- **Keputusan desain — hapus dependensi**: tanggal tugas **TETAP di posisi
  terakhir** (ala GanttProject), tidak kembali ke `start_date` awal. Alasannya:
  selama dependensi aktif, fase 3 selalu menulis jadwal hasil dependensi ke
  kolom `start_date` tugas — jadi cabang "tanpa predecessor pakai start_date
  sendiri" otomatis berarti memakai posisi terakhir. Event `deleted` **tetap**
  memicu `scheduleProject()` agar tugas lain yang terhubung ikut dihitung ulang.
- Domain jadwal terisolasi dari request/view: hanya `scheduleProject()` dan
  `assertNoCycle()` yang publik; sisanya privat.

## Rencana UI bertahap

1. ✅ Proyek (CRUD), pengaturan weekend, hari libur proyek.
2. ✅ Tugas + sub-tugas (CRUD, jadwal otomatis via CalendarService, cegah loop).
3. Sumber daya + alokasi ke tugas + hari libur anggota.
4. Dependensi antar tugas (FS/SS/FF/SF) — ✅ logika penjadwalan selesai (tahap 3A); UI form dependensi menyusul (tahap 3B).
5. Chart Gantt (bar per tugas, milestone, panah dependensi).

## Cara menjalankan & mengetes

```bash
php artisan migrate          # buat semua tabel termasuk upgrade tasks (000008)
php artisan serve            # http://127.0.0.1:8000
php artisan test --filter=CalendarServiceTest   # uji logika kalender
php artisan test --filter=TaskManagementTest    # uji CRUD tugas & agregasi induk
php artisan test --filter=SchedulerServiceTest  # uji mesin penjadwalan dependensi
```

Pengujian manual lewat browser:

1. Buka `http://127.0.0.1:8000` → beranda SIMPRO (kartu statistik + proyek terbaru).
2. Buat proyek (mis. mulai 2026-10-05, weekend Sabtu-Minggu, mode `no_work`).
3. Di halaman detail, tambah libur: tanggal `2026-10-15`, jenis **Sekali**, lalu
   coba tanggal sama lagi → muncul pesan "sudah digunakan".
4. Tambah libur berulang (mis. 1 Juni, jenis **Berulang**) → muncul tiap tahun.
5. Ubah mode weekend ke `work` → hari weekend kembali dihitung hari kerja
   (terasa saat nanti fitur durasi tugas diaktifkan; unit test sudah mengujinya).
6. Buka **Kelola Tugas** → buat tugas induk (tanpa tanggal), lalu sub-tugas
   dengan mulai 2026-10-12 dan durasi 5 → kolom selesai otomatis 2026-10-16
   dan tanggal induk terisi otomatis dari anak.
