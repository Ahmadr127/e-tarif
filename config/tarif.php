<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pengecualian bendera Surgery Type (OK / NON OK) pada export tarif
    |--------------------------------------------------------------------------
    |
    | Deskripsi yang memuat kata "tindakan"/"bedah" menjadi OK, KECUALI
    | bila memuat salah satu kata di bawah ini (nama alat/bahan, mis.
    | "Pisau Bedah") — perbandingan whole-word, case-insensitive.
    | Tambah kata baru di sini tanpa menyentuh kode.
    |
    */

    'surgery' => [
        'excluded_words' => [
            'pisau',
            'foto',
            'benang',
            'gunting',
            'pinset',
            'klem',
            'jarum',
            'hecting',
            'catgut',
            'masker',
            'baju',
            'topi',
            'doek',
            'sarung',
            'handscoen',
            'handschoen',
        ],
    ],

];
