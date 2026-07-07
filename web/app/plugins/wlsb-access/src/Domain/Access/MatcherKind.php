<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Access;

/**
 * How an access rule selects the requests it applies to. Specificity orders
 * precedence: a more specific match wins over a broader one.
 */
enum MatcherKind: string
{
    case Any = 'any';
    case UrlPrefix = 'url_prefix';
    case PostType = 'post_type';
    case PostId = 'post_id';

    public function specificity(): int
    {
        return match ($this) {
            self::PostId => 3,
            self::PostType => 2,
            self::UrlPrefix => 1,
            self::Any => 0,
        };
    }
}
