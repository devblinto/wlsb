<?php

declare(strict_types=1);

namespace Wlsb\Access\Domain\Mail;

/**
 * Sends a transactional email. Returns false on delivery failure rather than
 * throwing, so a failed notification never breaks the flow that triggered it
 * (registration still succeeds even if the email cannot be sent).
 */
interface Mailer
{
    public function send(EmailMessage $message): bool;
}
