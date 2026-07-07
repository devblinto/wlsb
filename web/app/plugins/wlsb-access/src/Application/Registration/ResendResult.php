<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Registration;

/**
 * Outcome of a request to resend the verification email.
 */
enum ResendResult
{
    case Sent;
    case Throttled;
    case NotPending;
}
