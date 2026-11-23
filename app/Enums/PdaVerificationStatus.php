<?php

declare(strict_types=1);

namespace App\Enums;

enum PdaVerificationStatus: string
{
    case Verified = 'verified';
    case VerificationFailed = 'verification_failed';
}
