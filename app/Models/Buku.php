<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Buku extends Model
{
    protected $table = 'buku';

    protected $primaryKey = 'id_buku';

    protected $fillable = [
        'id_dosen',
        'judul_buku',
        'isbn',
        'penerbit',
        'tahun',
        'anggota',
        'bidang_ilmu',
        'jenis_buku',
        'deskripsi',
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'id_dosen', 'id_dosen');
    }
}
