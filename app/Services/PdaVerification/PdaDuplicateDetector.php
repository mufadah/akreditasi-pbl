<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\PdaVerificationCode;
use App\Models\PdaRecord;

/**
 * Deteksi duplikat berbasis kunci ternormalisasi judul + penulis + tahun,
 * terhadap record terverifikasi di database maupun antar-record dalam batch yang sama.
 */
class PdaDuplicateDetector
{
    /** @var array<string, int> key => index record pertama di batch */
    private array $batchKeys = [];

    public static function makeKey(?string $judul, ?string $penulis, ?string $tahun): ?string
    {
        if ($judul === null || $penulis === null || $tahun === null) {
            return null;
        }

        $normalized = implode('|', array_map(
            fn (string $value): string => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value))),
            [$judul, $penulis, $tahun],
        ));

        return hash('sha256', $normalized);
    }

    public function reset(): void
    {
        $this->batchKeys = [];
    }

    public function detect(string $key): ?VerificationIssue
    {
        if (isset($this->batchKeys[$key])) {
            $position = $this->batchKeys[$key] + 1;

            return new VerificationIssue(
                PdaVerificationCode::Duplicate,
                'judul,penulis,tahun',
                "Duplikat dengan record #{$position} pada batch yang sama (kombinasi judul + penulis + tahun identik).",
            );
        }

        $existingId = PdaRecord::query()->verified()->where('duplicate_key', $key)->value('id');

        if ($existingId !== null) {
            return new VerificationIssue(
                PdaVerificationCode::Duplicate,
                'judul,penulis,tahun',
                "Duplikat dengan data terverifikasi di database (pda_records #{$existingId}) dengan kombinasi judul + penulis + tahun identik.",
            );
        }

        return null;
    }

    /**
     * Daftarkan kunci record yang lolos verifikasi agar record berikutnya di batch terdeteksi sebagai duplikat.
     */
    public function remember(string $key, int $index): void
    {
        $this->batchKeys[$key] ??= $index;
    }
}
