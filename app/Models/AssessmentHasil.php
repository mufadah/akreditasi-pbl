<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentHasil extends Model
{
    protected $table = 'assessment_hasil';
    protected $primaryKey = 'id_assessment';

    protected $fillable = [
        'kode_indikator',
        'versi_instrumen',
        'nilai_metrik',
        'skor_capaian',
        'skor_target',
        'status',
        'gap',
        'rekomendasi',
        'dikirim_ke_k3_at',
    ];
}
