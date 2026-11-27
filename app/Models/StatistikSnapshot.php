<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatistikSnapshot extends Model
{
    protected $table = 'statistik_snapshot';

    protected $primaryKey = 'id_snapshot';

    protected $fillable = ['tanggal', 'tahun', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}