<?php

namespace Database\Factories;

use App\Enums\JenisPublikasiPda;
use App\Enums\PdaVerificationCode;
use App\Enums\PdaVerificationStatus;
use App\Models\PdaRecord;
use App\Services\PdaVerification\PdaDuplicateDetector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PdaRecord>
 */
class PdaRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $judul = fake()->sentence(6);
        $penulis = fake()->name();
        $tahun = (string) fake()->numberBetween(2018, 2026);

        return [
            'judul' => $judul,
            'penulis' => $penulis,
            'tahun' => $tahun,
            'jenis' => fake()->randomElement(JenisPublikasiPda::values()),
            'link_universal' => fake()->url(),
            'duplicate_key' => PdaDuplicateDetector::makeKey($judul, $penulis, $tahun),
            'verification_status' => PdaVerificationStatus::Verified,
            'verification_codes' => [],
            'verification_notes' => null,
            'verified_at' => now(),
        ];
    }

    public function verificationFailed(PdaVerificationCode $code = PdaVerificationCode::InvalidLink): static
    {
        return $this->state(fn (): array => [
            'verification_status' => PdaVerificationStatus::VerificationFailed,
            'verification_codes' => [$code->value],
            'verification_notes' => "[{$code->value}] Gagal verifikasi.",
            'verified_at' => null,
        ]);
    }
}
