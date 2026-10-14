<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Validasi ubah tugas.
 *
 * Selain aturan sama dengan StoreTaskRequest, ditambah cegah loop induk:
 * tugas tidak boleh menjadi induk dirinya sendiri maupun turunannya.
 */
class UpdateTaskRequest extends StoreTaskRequest
{
    /** Cegah pemilihan induk yang membentuk siklus induk-anak. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
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
