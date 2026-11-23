<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\JenisPublikasiPda;
use App\Enums\PdaVerificationCode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Menjalankan seluruh tahap verifikasi (struktur, link, duplikat) tanpa menulis ke database.
 */
class PdaRecordVerifier
{
    public function __construct(
        private readonly PdaLinkValidator $linkValidator,
        private readonly PdaDuplicateDetector $duplicateDetector,
    ) {}

    /**
     * @param  iterable<int, mixed>  $records
     * @return array<int, PdaRecordVerificationResult>
     */
    public function verifyBatch(iterable $records): array
    {
        $this->duplicateDetector->reset();
        $results = [];
        $index = 0;

        foreach ($records as $record) {
            $results[] = $this->verify(PdaRecordData::fromArray(is_array($record) ? $record : []), $index);
            $index++;
        }

        return $results;
    }

    private function verify(PdaRecordData $record, int $index): PdaRecordVerificationResult
    {
        $issues = $this->structureIssues($record);

        $hasLinkIssue = array_filter($issues, fn (VerificationIssue $issue): bool => $issue->field === 'link_universal') !== [];
        if (! $hasLinkIssue && $record->linkUniversal !== null) {
            $linkIssue = $this->linkValidator->validate($record->linkUniversal);
            if ($linkIssue !== null) {
                $issues[] = $linkIssue;
            }
        }

        $duplicateKey = $this->hasValidKeyFields($issues)
            ? PdaDuplicateDetector::makeKey($record->judul, $record->penulis, $record->tahun)
            : null;

        if ($duplicateKey !== null) {
            $duplicateIssue = $this->duplicateDetector->detect($duplicateKey);

            if ($duplicateIssue !== null) {
                $issues[] = $duplicateIssue;
            } elseif ($issues === []) {
                $this->duplicateDetector->remember($duplicateKey, $index);
            }
        }

        return new PdaRecordVerificationResult($index, $record, $issues, $duplicateKey);
    }

    /**
     * @return array<int, VerificationIssue>
     */
    private function structureIssues(PdaRecordData $record): array
    {
        $validator = Validator::make($record->toValidationArray(), [
            'judul' => ['required', 'string'],
            'penulis' => ['required', 'string'],
            'tahun' => ['required', 'regex:/^\d{4}$/'],
            'jenis' => ['required', Rule::enum(JenisPublikasiPda::class)],
            'link_universal' => ['required', 'url:http,https'],
        ], [
            'required' => 'Field :attribute wajib diisi.',
            'tahun.regex' => 'Field tahun harus tepat 4 digit angka (YYYY), diterima: ":input".',
            'jenis.enum' => 'Field jenis harus salah satu dari: '.implode(', ', JenisPublikasiPda::values()).'; diterima: ":input".',
            'link_universal.url' => 'Format URL link_universal tidak valid: ":input".',
        ]);

        if ($validator->passes()) {
            return [];
        }

        $failedRules = $validator->failed();
        $issues = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            $code = $field === 'link_universal' && isset($failedRules[$field]['Url'])
                ? PdaVerificationCode::MalformedLink
                : PdaVerificationCode::MissingField;

            foreach ($messages as $message) {
                $issues[] = new VerificationIssue($code, $field, $message);
            }
        }

        return $issues;
    }

    /**
     * @param  array<int, VerificationIssue>  $issues
     */
    private function hasValidKeyFields(array $issues): bool
    {
        foreach ($issues as $issue) {
            if (in_array($issue->field, ['judul', 'penulis', 'tahun'], true)) {
                return false;
            }
        }

        return true;
    }
}
