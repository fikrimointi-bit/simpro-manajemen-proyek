<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Validasi ubah tugas.
 *
 * Selain aturan sama dengan StoreTaskRequest, ditambah cegah loop induk:
 * tugas tidak boleh menjadi induk dirinya sendiri maupun turunannya, dan
 * predecessor yang membentuk siklus (A → B → A) ditolak sebagai error form.
 */
class UpdateTaskRequest extends StoreTaskRequest
{
    /** Cegah loop induk + baris predecessor yang membentuk siklus dependensi. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // diturunkan dari StoreTaskRequest: leaf, bukan diri sendiri, no siklus
            $this->validasiPredecessor($validator);

            $task = $this->route('task');
            $parentId = (int) $this->input('parent_id', 0);

            if (! $task || ! $parentId) {
                return; // tanpa induk, tidak mungkin terjadi siklus
            }

            if ($parentId === (int) $task->id) {
                $validator->errors()->add('parent_id', 'Tugas tidak boleh menjadi induknya sendiri.');

                return;
            }

            if (in_array($parentId, $task->descendantIds(), true)) {
                $validator->errors()->add('parent_id', 'Tugas tidak boleh menjadi induk turunannya sendiri (akan membentuk siklus).');
            }
        });
    }
}
