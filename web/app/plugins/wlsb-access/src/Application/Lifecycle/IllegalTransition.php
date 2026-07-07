<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Lifecycle;

use RuntimeException;

/**
 * Thrown when a status change is attempted that the transition table forbids.
 */
final class IllegalTransition extends RuntimeException {}
