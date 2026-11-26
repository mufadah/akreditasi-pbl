<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\JenisPublikasiPda;

/**
 * Representasi ternormalisasi (trim, string) dari satu record mentah PDA.
 */
final readonly class PdaRecordData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ?string $judul,
        public ?string $penulis,
        public ?string $tahun,
        public ?string $jenis,
        public ?string $linkUniversal,
        public array $payload = [],
    ) {}

    /**
     * @param  array<string, mixed>  $record
     */
    public static function fromArray(array $record): self
    {
        $jenis = self::normalize($record['jenis'] ?? null);

        return new self(
            judul: self::normalize($record['judul'] ?? null),
            penulis: self::normalize($record['penulis'] ?? null),
            tahun: self::normalize($record['tahun'] ?? null),
            jenis: $jenis !== null ? (JenisPublikasiPda::tryFromLabel($jenis)?->value ?? $jenis) : null,
            linkUniversal: self::normalize($record['link_universal'] ?? null),
            payload: $record,
        );
    }

    /**
     * @return array{judul: ?string, penulis: ?string, tahun: ?string, jenis: ?string, link_universal: ?string}
     */
    public function toValidationArray(): array
    {
        return [
            'judul' => $this->judul,
            'penulis' => $this->penulis,
            'tahun' => $this->tahun,
            'jenis' => $this->jenis,
            'link_universal' => $this->linkUniversal,
        ];
    }

    private static function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
