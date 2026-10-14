<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi simpan tugas.
 *
 * Aturan kondisional: tugas induk (parent_id kosong) tidak mengisi tanggal &
 * durasi karena dihitung dari sub-tugas; tugas anak wajib mengisi keduanya.
 */
class StoreTaskRequest extends FormRequest
{
    /** Tidak ada autentikasi, semua orang boleh mengelola tugas. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = $this->route('project');

        // tugas induk (punya sub-tugas) tidak pernah mengisi tanggal & durasi,
        // bahkan ketika induk tersebut dijadikan anak tugas lain
        $task = $this->route('task');
        $tanpaAnak = ! $task || ! $task->subtasks()->exists();
        $butuhJadwal = (bool) $this->input('parent_id') && $tanpaAnak;

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
            'start_date' => $butuhJadwal
                ? ['required', 'date']
                : ['nullable', 'date', 'required_with:duration'],
            'duration' => $butuhJadwal
                ? ['required', 'integer', 'min:1', 'max:3660']
                : ['nullable', 'integer', 'min:1', 'max:3660', 'required_with:start_date'],
        ];
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
        ];
    }

    public function messages(): array
    {
        return [
            'color.regex' => 'Format warna harus heksadesimal, contoh #3b82f6.',
            'parent_id.exists' => 'Tugas induk yang dipilih tidak ditemukan dalam proyek ini.',
        ];
    }
}
