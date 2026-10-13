<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    protected $fillable = [
        'user_id',
        'tipe',
        'judul',
        'pesan',
        'link',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}
