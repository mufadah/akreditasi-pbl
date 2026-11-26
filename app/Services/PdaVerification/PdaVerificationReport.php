<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\PdaVerificationCode;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Laporan verifikasi satu batch/sinkronisasi data PDA.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PdaVerificationReport implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int, PdaRecordVerificationResult>  $results
     */
    public function __construct(public array $results) {}

    public function totalRecords(): int
    {
        return count($this->results);
    }

    public function passedCount(): int
    {
        return count(array_filter($this->results, fn (PdaRecordVerificationResult $result): bool => $result->passed()));
    }

    public function failedCount(): int
    {
        return $this->totalRecords() - $this->passedCount();
    }

    public function countByCode(PdaVerificationCode $code): int
    {
        return count(array_filter($this->results, fn (PdaRecordVerificationResult $result): bool => $result->hasCode($code)));
    }

    /**
     * @return array<int, PdaRecordVerificationResult>
     */
    public function problematicRecords(): array
    {
        return array_values(array_filter($this->results, fn (PdaRecordVerificationResult $result): bool => ! $result->passed()));
    }

    /**
     * @return array{integrity_score: float, level: string, summary: string}
     */
    public function dataIntegrityImpact(): array
    {
        $total = $this->totalRecords();

        if ($total === 0) {
            return ['integrity_score' => 100.0, 'level' => 'none', 'summary' => 'Tidak ada record yang diproses.'];
        }

        $failed = $this->failedCount();
        $score = round(($this->passedCount() / $total) * 100, 2);
        $failedRatio = $failed / $total;

        $level = match (true) {
            $failed === 0 => 'none',
            $failedRatio <= 0.10 => 'low',
            $failedRatio <= 0.30 => 'medium',
            default => 'high',
        };

        $breakdown = [];
        foreach (PdaVerificationCode::cases() as $code) {
            $count = $this->countByCode($code);
            if ($count > 0) {
                $breakdown[] = "{$count} {$code->value}";
            }
        }

        $summary = $failed === 0
            ? "Seluruh {$total} record lolos verifikasi; integritas data terjaga."
            : sprintf(
                '%d dari %d record (%s%%) gagal verifikasi (%s) dan ditahan dengan status verification_failed sehingga tidak mencemari data terverifikasi. Dampak integritas: %s.',
                $failed,
                $total,
                rtrim(rtrim(number_format($failedRatio * 100, 2, '.', ''), '0'), '.'),
                implode(', ', $breakdown),
                $level,
            );

        return ['integrity_score' => $score, 'level' => $level, 'summary' => $summary];
    }

    /**
     * @return array{summary: array{total_records: int, passed_count: int, failed_count: int, duplicate_count: int, invalid_link_count: int, missing_field_count: int, malformed_link_count: int, data_integrity_impact: array{integrity_score: float, level: string, summary: string}}, problematic_records: array<int, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'summary' => [
                'total_records' => $this->totalRecords(),
                'passed_count' => $this->passedCount(),
                'failed_count' => $this->failedCount(),
                'duplicate_count' => $this->countByCode(PdaVerificationCode::Duplicate),
                'invalid_link_count' => $this->countByCode(PdaVerificationCode::InvalidLink),
                'missing_field_count' => $this->countByCode(PdaVerificationCode::MissingField),
                'malformed_link_count' => $this->countByCode(PdaVerificationCode::MalformedLink),
                'data_integrity_impact' => $this->dataIntegrityImpact(),
            ],
            'problematic_records' => array_map(
                fn (PdaRecordVerificationResult $result): array => $result->toArray(),
                $this->problematicRecords(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
