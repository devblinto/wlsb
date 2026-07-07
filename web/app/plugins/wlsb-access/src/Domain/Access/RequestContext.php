<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Access;

/**
 * The resolved front-end request, built by the FrontendGuard from the queried
 * object and URL, and matched against access rules.
 */
final class RequestContext
{
    public function __construct(
        public readonly string $path,
        public readonly ?int $postId = null,
        public readonly ?string $postType = null,
        public readonly bool $isFrontPage = false,
    ) {}
}
