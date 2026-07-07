<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Registration;

/**
 * Outcome of consuming an email-verification token.
 */
enum VerificationResult
{
    case Verified;
    case Expired;
    case Invalid;
    case AlreadyVerified;
}
