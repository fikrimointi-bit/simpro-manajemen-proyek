<?php

return [

    /*
    |--------------------------------------------------------------------
    | Pesan validasi bahasa Indonesia
    |--------------------------------------------------------------------
    | Dipakai otomatis karena AppServiceProvider menyetel locale 'id'.
    */

    'accepted' => ':attribute harus diterima.',
    'after' => ':attribute harus tanggal setelah :date.',
    'array' => ':attribute harus berupa array.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'boolean' => ':attribute harus bernilai true atau false.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus tanggal yang sama dengan :date.',
    'different' => ':attribute dan :other harus berbeda.',
    'email' => ':attribute bukan alamat email yang valid.',
    'filled' => ':attribute wajib diisi.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'max' => [
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
    ],
    'min' => [
        'string' => ':attribute minimal :min karakter.',
        'numeric' => ':attribute minimal :min.',
    ],
    'not_in' => ':attribute yang dipilih tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi ketika :other bernilai :value.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah digunakan.',
    'url' => ':attribute bukan URL yang valid.',

    'custom' => [],

    'attributes' => [
        'name' => 'nama',
        'organization' => 'organisasi',
        'website' => 'situs web',
        'description' => 'deskripsi',
        'start_date' => 'tanggal mulai',
        'weekend_days' => 'hari weekend',
        'weekend_mode' => 'mode weekend',
        'date' => 'tanggal',
        'type' => 'jenis',
    ],

];
