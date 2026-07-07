<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Access;

use RuntimeException;

/**
 * Thrown when a custom-role create/rename/delete request is invalid (bad input,
 * a name collision, or an attempt to modify a role that is not an admin-created
 * custom role). Carries a human-readable, already-translated message suitable
 * for surfacing as an admin notice.
 */
final class InvalidRoleOperation extends RuntimeException {}
