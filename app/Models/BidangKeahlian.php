<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BidangKeahlian extends Model
{
    protected $table = "bidang_keahlian";
    protected $primaryKey = "id_bidang_keahlian";
    protected $guarded = ["id_bidang_keahlian"];

    public function dosen(): BelongsToMany
    {
        return $this->belongsToMany(Dosen::class, 'dosen', 'id_bidang_keahlian', 'id_dosen');
    }
}
