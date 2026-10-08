<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KonfigurasiMetrik extends Model
{
    protected $table = 'konfigurasi_metrik';
    protected $primaryKey = 'id_metrik';

    protected $fillable = [
        'kode_indikator',
        'nama_indikator',
        'bidang',
        'rumus_formula',
        'ambang_target',
        'jenjang',
    ];
}
