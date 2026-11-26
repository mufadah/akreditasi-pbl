<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\PdaVerificationCode;
use App\Enums\PdaVerificationStatus;

/**
 * Hasil verifikasi satu record PDA (belum tentu sudah disimpan).
 */
final class PdaRecordVerificationResult
{
    /**
     * @param  array<int, VerificationIssue>  $issues
     */
    public function __construct(
        public readonly int $index,
        public readonly PdaRecordData $record,
        public readonly array $issues,
        public readonly ?string $duplicateKey,
        public ?int $storedId = null,
    ) {}

    public function passed(): bool
    {
        return $this->issues === [];
    }

    public function status(): PdaVerificationStatus
    {
        return $this->passed() ? PdaVerificationStatus::Verified : PdaVerificationStatus::VerificationFailed;
    }

    public function hasCode(PdaVerificationCode $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    /**
     * @return array<int, PdaVerificationCode>
     */
    public function codes(): array
    {
        $codes = [];

        foreach ($this->issues as $issue) {
            if (! in_array($issue->code, $codes, true)) {
                $codes[] = $issue->code;
            }
        }

        return $codes;
    }

    public function notes(): ?string
    {
        if ($this->passed()) {
            return null;
        }

        return implode(' | ', array_map(
            fn (VerificationIssue $issue): string => "[{$issue->code->value}] {$issue->message}",
            $this->issues,
        ));
    }

    /**
     * @return array{index: int, id: ?int, judul: ?string, penulis: ?string, tahun: ?string, jenis: ?string, link_universal: ?string, status: string, codes: array<int, string>, issues: array<int, array{code: string, field: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'id' => $this->storedId,
            'judul' => $this->record->judul,
            'penulis' => $this->record->penulis,
            'tahun' => $this->record->tahun,
            'jenis' => $this->record->jenis,
            'link_universal' => $this->record->linkUniversal,
            'status' => $this->status()->value,
            'codes' => array_map(fn (PdaVerificationCode $code): string => $code->value, $this->codes()),
            'issues' => array_map(fn (VerificationIssue $issue): array => $issue->toArray(), $this->issues),
        ];
    }
}
