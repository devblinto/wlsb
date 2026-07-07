<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Access;

/**
 * What happens when an access rule blocks a request. Rules are restriction-only,
 * so there is no "allow" effect — grants come from capabilities.
 */
enum RuleEffect: string
{
    case Deny = 'deny';          // 403
    case Redirect = 'redirect';  // send elsewhere
    case RequireLogin = 'login'; // send to the login page
}
