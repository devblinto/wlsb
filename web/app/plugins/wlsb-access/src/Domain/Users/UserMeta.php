<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Users;

/**
 * Canonical user-meta keys for the account lifecycle. Status and requested role
 * have dedicated UserDirectory methods; these are the remaining token fields.
 */
final class UserMeta
{
    public const STATUS = 'wlsb_access_status';

    public const STATUS_UPDATED = 'wlsb_access_status_updated';

    public const REQUESTED_ROLE = 'wlsb_access_requested_role';

    public const TOKEN_HASH = 'wlsb_access_email_token_hash';

    public const TOKEN_EXPIRES = 'wlsb_access_email_token_expires';

    public const TOKEN_CREATED = 'wlsb_access_email_token_created';

    private function __construct() {}
}
