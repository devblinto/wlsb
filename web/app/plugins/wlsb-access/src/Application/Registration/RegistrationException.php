<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Registration;

use RuntimeException;

/**
 * Thrown for invalid registration input (bad email, unavailable role, taken
 * username). Carries an already-translated, user-safe message.
 *
 * Note: an already-registered EMAIL never throws — it is handled generically to
 * avoid account enumeration.
 */
final class RegistrationException extends RuntimeException {}
