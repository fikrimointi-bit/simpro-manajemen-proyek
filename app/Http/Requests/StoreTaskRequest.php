<?php

namespace App\Http\Requests;

use App\Exceptions\SchedulingException;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\SchedulerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi simpan tugas.
 *
 * Aturan kondisional: tugas induk (parent_id kosong) tidak mengisi tanggal &
 * durasi karena dihitung dari sub-tugas; tugas anak wajib mengisi keduanya.
 * Bila ada predecessor (tahap 3B), tanggal mulai TIDAK wajib karena input
 * dinonaktifkan dan jadwalnya dihitung otomatis oleh SchedulerService.
 */
class StoreTaskRequest extends FormRequest
{
    /** Tidak ada autentikasi, semua orang boleh mengelola tugas. */
    public function authorize(): bool
    {
        return true;
    }

    /** Buang baris predecessor yang pendahulunya kosong lalu rapikan urutannya. */
    protected function prepareForValidation(): void
    {
        $baris = $this->input('predecessors');

        if (! is_array($baris)) {
            return; // tipe salah biar aturan 'array' yang menangani
        }

        $terisi = array_values(array_filter(
            $baris,
            fn ($b): bool => is_array($b) && ! empty($b['predecessor_id'])
        ));

        $this->merge(['predecessors' => $terisi]);
    }

    public function rules(): array
    {
        $project = $this->route('project');

        // tugas induk (punya sub-tugas) tidak pernah mengisi tanggal & durasi,
        // bahkan ketika induk tersebut dijadikan anak tugas lain
        $task = $this->route('task');
        $tanpaAnak = ! $task || ! $task->subtasks()->exists();
        $butuhJadwal = (bool) $this->input('parent_id') && $tanpaAnak;

        // ada baris predecessor = tanggal mulai dinonaktifkan di form dan
        // dihitung dari pendahulu, jadi cukup durasi yang wajib diisi
        $adaPendahulu = is_array($this->input('predecessors')) && $this->input('predecessors') !== [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'priority' => ['required', 'in:rendah,normal,tinggi'],
            // induk harus tugas dalam proyek yang sama (bukan lintas proyek)
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where('project_id', $project->id),
            ],
            // hanya tugas anak tanpa sub-tugas yang WAJIB mengisi jadwal;
            // tugas mandiri/tanpa induk boleh mengosongkannya (jadi tugas induk),
            // tapi jika mengisi, harus lengkap keduanya (required_with)
            'start_date' => $butuhJadwal && ! $adaPendahulu
                ? ['required', 'date']
                : ($adaPendahulu
                    ? ['nullable', 'date'] // dinonaktifkan di form: dihitung dari predecessor
                    : ['nullable', 'date', 'required_with:duration']),
            'duration' => ($butuhJadwal || $adaPendahulu)
                ? ['required', 'integer', 'min:1', 'max:3660']
                : ['nullable', 'integer', 'min:1', 'max:3660', 'required_with:start_date'],
            // baris predecessor (tahap 3B): pendahulu, tipe FS/SS/FF/SF, delay hari kerja
            'predecessors' => ['nullable', 'array'],
            'predecessors.*.predecessor_id' => [
                'required',
                'integer',
                'distinct', // satu pasangan tugas cukup satu baris (juga kunci unik tabel)
                Rule::exists('tasks', 'id')->where('project_id', $project->id),
            ],
            'predecessors.*.type' => ['required', Rule::in(['FS', 'SS', 'FF', 'SF'])],
            'predecessors.*.lag_days' => ['required', 'integer', 'min:-3660', 'max:3660'],
            // penugasan anggota (tahap 4B): hanya sumber daya dari proyek yang sama saja
            'resource_ids' => ['nullable', 'array'],
            'resource_ids.*' => [
                'integer',
                'distinct', // satu anggota cukup satu baris penugasan
                Rule::exists('resources', 'id')->where('project_id', $project->id),
            ],
        ];
    }

    /** Tolak baris predecessor yang melanggar aturan sebelum masuk database. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validasiPredecessor($validator);
        });
    }

    /**
     * Validasi pendahulu tiap baris: bukan diri sendiri, harus tugas tanpa
     * sub-tugas (dependensi hanya antar leaf), dan tidak membentuk siklus
     * (A → B → A). Siklus ditolak sebagai error form lewat
     * SchedulerService::assertNoCycle() — bukan halaman error.
     */
    protected function validasiPredecessor(Validator $validator): void
    {
        $project = $this->route('project');
        $task = $this->route('task'); // null saat simpan tugas baru (belum punya id)
        $baris = $this->input('predecessors');

        if (! is_array($baris)) {
            return;
        }

        $scheduler = app(SchedulerService::class);

        foreach ($baris as $i => $row) {
            $kunci = "predecessors.$i.predecessor_id";
            $pendahuluId = (int) ($row['predecessor_id'] ?? 0);

            if (! $pendahuluId) {
                continue; // baris kosong sudah dibuang saat prepareForValidation
            }

            if ($task && $pendahuluId === (int) $task->id) {
                $validator->errors()->add($kunci, 'Tugas tidak boleh menjadi pendahulunya sendiri.');

                continue;
            }

            $pendahulu = Task::where('project_id', $project->id)
                ->whereKey($pendahuluId)
                ->first();

            if (! $pendahulu) {
                continue; // aturan 'exists' sudah melaporkan
            }

            if ($pendahulu->subtasks()->exists()) {
                $validator->errors()->add($kunci, 'Tugas pendahulu harus tugas tanpa sub-tugas (bukan tugas induk).');

                continue;
            }

            // siklus mustahil saat tugas baru: semua baris menuju tugas baru
            // yang belum punya dependensi keluar
            if (! $task) {
                continue;
            }

            try {
                $scheduler->assertNoCycle(new TaskDependency([
                    'project_id' => $project->id,
                    'predecessor_id' => $pendahuluId,
                    'successor_id' => $task->id,
                    'type' => (string) ($row['type'] ?? 'FS'),
                    'lag_days' => (int) ($row['lag_days'] ?? 0),
                ]));
            } catch (SchedulingException $e) {
                $validator->errors()->add($kunci, $e->getMessage());
            }
        }
    }

    /** Nama atribut & pesan khusus berbahasa Indonesia. */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'color' => 'warna',
            'priority' => 'prioritas',
            'parent_id' => 'tugas induk',
            'start_date' => 'tanggal mulai',
            'duration' => 'durasi',
            'predecessors' => 'predecessor',
            'predecessors.*.predecessor_id' => 'tugas pendahulu',
            'predecessors.*.type' => 'tipe dependensi',
            'predecessors.*.lag_days' => 'delay hari kerja',
            'resource_ids' => 'penugasan anggota',
            'resource_ids.*' => 'penugasan anggota',
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'Format warna harus heksadesimal, contoh #3b82f6.',
            'parent_id.exists' => 'Tugas induk yang dipilih tidak ditemukan dalam proyek ini.',
            'predecessors.*.predecessor_id.exists' => 'Tugas pendahulu yang dipilih tidak ditemukan dalam proyek ini.',
            'predecessors.*.predecessor_id.distinct' => 'Tugas pendahulu sama terpilih lebih dari satu kali.',
            'predecessors.*.type.in' => 'Tipe dependensi harus salah satu dari FS, SS, FF, atau SF.',
            'predecessors.*.lag_days.min' => 'Delay harus antara -3660 sampai 3660 hari kerja.',
            'predecessors.*.lag_days.max' => 'Delay harus antara -3660 sampai 3660 hari kerja.',
            'resource_ids.*.exists' => 'Anggota yang dipilih bukan bagian dari proyek ini.',
            'resource_ids.*.distinct' => 'Anggota sama terpilih lebih dari satu kali.',
        ];
    }
}
