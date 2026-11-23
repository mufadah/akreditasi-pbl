<?php

declare(strict_types=1);

namespace App\Enums;

enum PdaVerificationCode: string
{
    case InvalidLink = 'INVALID_LINK';
    case Duplicate = 'DUPLICATE';
    case MissingField = 'MISSING_FIELD';
    case MalformedLink = 'MALFORMED_LINK';
}
