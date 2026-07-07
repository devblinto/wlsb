<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Mail;

/**
 * A transactional email to send. `key` names the template (verification,
 * welcome, …) for auditing and for the mailer's filter hooks.
 */
final class EmailMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text = '',
        public readonly string $key = '',
    ) {}
}
