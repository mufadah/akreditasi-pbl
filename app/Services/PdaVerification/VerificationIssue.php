<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\PdaVerificationCode;

/**
 * Satu temuan masalah verifikasi pada sebuah record PDA.
 */
final readonly class VerificationIssue
{
    public function __construct(
        public PdaVerificationCode $code,
        public string $field,
        public string $message,
    ) {}

    /**
     * @return array{code: string, field: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code->value,
            'field' => $this->field,
            'message' => $this->message,
        ];
    }
}
