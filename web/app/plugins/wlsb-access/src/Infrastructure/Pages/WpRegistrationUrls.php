<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Pages;

use Wlsb\Access\Application\Registration\RegistrationUrls;

/**
 * Builds the front-end lifecycle URLs from the provisioned pages. The verify URL
 * carries the user id and raw token as query args, which the verification
 * shortcode reads.
 */
final class WpRegistrationUrls implements RegistrationUrls
{
    public function __construct(private readonly PageProvisioner $pages) {}

    public function verify(int $userId, string $rawToken): string
    {
        return add_query_arg(
            ['wlsb_uid' => $userId, 'wlsb_token' => $rawToken],
            $this->pages->url('verify'),
        );
    }

    public function login(): string
    {
        return $this->pages->url('login');
    }

    public function status(): string
    {
        return $this->pages->url('status');
    }
}
