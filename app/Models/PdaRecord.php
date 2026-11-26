<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PdaVerificationStatus;
use Database\Factories\PdaRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Record data PDA yang sudah melalui verifikasi pra-insert.
 *
 * @property int $id
 * @property ?string $judul
 * @property ?string $penulis
 * @property ?string $tahun
 * @property ?string $jenis
 * @property ?string $link_universal
 * @property ?string $duplicate_key
 * @property PdaVerificationStatus $verification_status
 * @property ?array<int, string> $verification_codes
 * @property ?string $verification_notes
 * @property ?array<string, mixed> $payload
 */
class PdaRecord extends Model
{
    /** @use HasFactory<PdaRecordFactory> */
    use HasFactory;

    protected $table = 'pda_records';

    protected $fillable = [
        'judul',
        'penulis',
        'tahun',
        'jenis',
        'link_universal',
        'duplicate_key',
        'verification_status',
        'verification_codes',
        'verification_notes',
        'payload',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => PdaVerificationStatus::class,
            'verification_codes' => 'array',
            'payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PdaRecord>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('verification_status', PdaVerificationStatus::Verified);
    }

    /**
     * @param  Builder<PdaRecord>  $query
     */
    public function scopeVerificationFailed(Builder $query): void
    {
        $query->where('verification_status', PdaVerificationStatus::VerificationFailed);
    }
}
