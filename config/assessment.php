<?php

return [
    'penelitian' => [
        'PEN-01' => [
            'nama' => 'Rata-rata jumlah penelitian DTPS pada tahun penilaian',
            'metrik' => 'rata_kegiatan',
            'satuan' => 'penelitian per DTPS',
            'bobot' => 0.35,
            'rubrik' => [
                ['skor' => 4, 'min' => 1.0],
                ['skor' => 3, 'min' => 0.75],
                ['skor' => 2, 'min' => 0.5],
                ['skor' => 1, 'min' => 0.25],
            ],
        ],
        'PEN-02' => [
            'nama' => 'Persentase DTPS yang terlibat penelitian',
            'metrik' => 'persen_dosen_terlibat',
            'satuan' => 'persen DTPS terlibat',
            'bobot' => 0.25,
            'rubrik' => [
                ['skor' => 4, 'min' => 90],
                ['skor' => 3, 'min' => 70],
                ['skor' => 2, 'min' => 50],
                ['skor' => 1, 'min' => 25],
            ],
        ],
        'PEN-03' => [
            'nama' => 'Rata-rata luaran penelitian per DTPS',
            'metrik' => 'rata_luaran',
            'satuan' => 'luaran per DTPS',
            'bobot' => 0.25,
            'rubrik' => [
                ['skor' => 4, 'min' => 1.0],
                ['skor' => 3, 'min' => 0.75],
                ['skor' => 2, 'min' => 0.5],
                ['skor' => 1, 'min' => 0.25],
            ],
        ],
        'PEN-04' => [
            'nama' => 'Rata-rata dana penelitian per DTPS',
            'metrik' => 'rata_dana',
            'satuan' => 'rupiah dana per DTPS',
            'bobot' => 0.15,
            'rubrik' => [
                ['skor' => 4, 'min' => 10000000],
                ['skor' => 3, 'min' => 7500000],
                ['skor' => 2, 'min' => 5000000],
                ['skor' => 1, 'min' => 2500000],
            ],
        ],
    ],
        'pkm' => [
        'PKM-01' => [
            'nama' => 'Rata-rata jumlah kegiatan PkM DTPS pada tahun penilaian',
            'metrik' => 'rata_kegiatan',
            'satuan' => 'kegiatan PkM per DTPS',
            'bobot' => 0.35,
            'rubrik' => [
                ['skor' => 4, 'min' => 1.0],
                ['skor' => 3, 'min' => 0.75],
                ['skor' => 2, 'min' => 0.5],
                ['skor' => 1, 'min' => 0.25],
            ],
        ],
        'PKM-02' => [
            'nama' => 'Persentase DTPS yang terlibat PkM',
            'metrik' => 'persen_dosen_terlibat',
            'satuan' => 'persen DTPS terlibat',
            'bobot' => 0.25,
            'rubrik' => [
                ['skor' => 4, 'min' => 90],
                ['skor' => 3, 'min' => 70],
                ['skor' => 2, 'min' => 50],
                ['skor' => 1, 'min' => 25],
            ],
        ],
        'PKM-03' => [
            'nama' => 'Rata-rata luaran PkM per DTPS',
            'metrik' => 'rata_luaran',
            'satuan' => 'luaran per DTPS',
            'bobot' => 0.25,
            'rubrik' => [
                ['skor' => 4, 'min' => 1.0],
                ['skor' => 3, 'min' => 0.75],
                ['skor' => 2, 'min' => 0.5],
                ['skor' => 1, 'min' => 0.25],
            ],
        ],
        'PKM-04' => [
            'nama' => 'Rata-rata dana PkM per DTPS',
            'metrik' => 'rata_dana',
            'satuan' => 'rupiah dana per DTPS',
            'bobot' => 0.15,
            'rubrik' => [
                ['skor' => 4, 'min' => 5000000],
                ['skor' => 3, 'min' => 3750000],
                ['skor' => 2, 'min' => 2500000],
                ['skor' => 1, 'min' => 1250000],
            ],
        ],
    ],
    'kerjasama' => [
        'KS-01' => [
            'nama' => 'Kerja Sama Tridharma Internasional dan Nasional',
            'metrik' => 'jumlah_internasional_nasional',
            'satuan' => 'kerja sama internasional dan nasional yang berlaku',
            'bobot' => 1.0,
            'rubrik' => [
                ['skor' => 4, 'min' => 8],
                ['skor' => 3, 'min' => 6],
                ['skor' => 2, 'min' => 4],
                ['skor' => 1, 'min' => 2],
            ],
        ],
    ],
];