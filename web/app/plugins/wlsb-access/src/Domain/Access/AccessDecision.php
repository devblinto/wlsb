<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Access;

/**
 * The outcome of evaluating access rules for a request. A null effect means the
 * request is allowed.
 */
final class AccessDecision
{
    private function __construct(
        public readonly ?RuleEffect $effect,
        public readonly ?string $redirect,
    ) {}

    public static function allow(): self
    {
        return new self(null, null);
    }

    public static function block(RuleEffect $effect, ?string $redirect = null): self
    {
        return new self($effect, $redirect);
    }

    public function isAllowed(): bool
    {
        return $this->effect === null;
    }
}
