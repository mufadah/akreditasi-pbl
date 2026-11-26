<?php

declare(strict_types=1);

namespace App\Enums;

enum JenisPublikasiPda: string
{
    case Jurnal = 'Jurnal';
    case Prosiding = 'Prosiding';
    case Buku = 'Buku';
    case Hki = 'HKI';
    case Produk = 'Produk';

    /**
     * Cocokkan label jenis secara case-insensitive (mis. "jurnal" -> Jurnal).
     */
    public static function tryFromLabel(string $label): ?self
    {
        $normalized = mb_strtolower(trim($label));

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->value) === $normalized) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
