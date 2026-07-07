<?php

declare(strict_types=1);

namespace Wlsb\Access\Tests\Support;

use Wlsb\Access\Domain\Mail\EmailMessage;
use Wlsb\Access\Domain\Mail\Mailer;

/**
 * Records sent messages; can be configured to simulate delivery failure.
 */
final class ArrayMailer implements Mailer
{
    /** @var list<EmailMessage> */
    public array $sent = [];

    public function __construct(private bool $succeeds = true) {}

    public function send(EmailMessage $message): bool
    {
        $this->sent[] = $message;

        return $this->succeeds;
    }

    public function last(): ?EmailMessage
    {
        return $this->sent[count($this->sent) - 1] ?? null;
    }
}
