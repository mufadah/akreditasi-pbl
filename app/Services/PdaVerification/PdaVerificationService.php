<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\PdaVerificationCode;
use App\Enums\PdaVerificationStatus;
use App\Models\PdaRecord;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi verifikasi pra-insert data PDA: seluruh record diverifikasi terlebih dahulu,
 * baru kemudian disimpan (verified / verification_failed) dalam satu transaksi.
 */
class PdaVerificationService
{
    public function __construct(private readonly PdaRecordVerifier $verifier) {}

    /**
     * Verifikasi tanpa menyimpan (dry-run).
     *
     * @param  iterable<int, mixed>  $records
     */
    public function verify(iterable $records): PdaVerificationReport
    {
        return new PdaVerificationReport($this->verifier->verifyBatch($records));
    }

    /**
     * Verifikasi lalu simpan seluruh record beserta status & alasan kegagalannya.
     *
     * @param  iterable<int, mixed>  $records
     */
    public function verifyAndStore(iterable $records): PdaVerificationReport
    {
        $results = $this->verifier->verifyBatch($records);

        DB::transaction(function () use ($results): void {
            foreach ($results as $result) {
                $result->storedId = $this->store($result)->id;
            }
        });

        return new PdaVerificationReport($results);
    }

    private function store(PdaRecordVerificationResult $result): PdaRecord
    {
        $record = $result->record;
        $passed = $result->passed();

        return PdaRecord::create([
            'judul' => $record->judul,
            'penulis' => $record->penulis,
            'tahun' => $this->limit($record->tahun, 20),
            'jenis' => $this->limit($record->jenis, 100),
            'link_universal' => $this->limit($record->linkUniversal, 2048),
            'duplicate_key' => $result->duplicateKey,
            'verification_status' => $passed ? PdaVerificationStatus::Verified : PdaVerificationStatus::VerificationFailed,
            'verification_codes' => array_map(fn (PdaVerificationCode $code): string => $code->value, $result->codes()),
            'verification_notes' => $result->notes(),
            'payload' => $record->payload,
            'verified_at' => $passed ? now() : null,
        ]);
    }

    private function limit(?string $value, int $length): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $length);
    }
}
