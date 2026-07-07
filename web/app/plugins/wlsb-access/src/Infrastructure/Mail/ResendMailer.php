<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Mail;

use Wlsb\Access\Domain\Mail\EmailMessage;
use Wlsb\Access\Domain\Mail\Mailer;

/**
 * Sends transactional email through the Resend HTTP API using WordPress's HTTP
 * client — no external Composer dependency. The API key and from-address come
 * from environment/config (RESEND_API_KEY, WLSB_MAIL_FROM, WLSB_MAIL_FROM_NAME).
 *
 * The payload builder is pure and unit-tested; only the send() call touches
 * WordPress. When the API key is not configured, send() fails fast (returns
 * false) so the NotificationService records a delivery failure without a request.
 */
final class ResendMailer implements Mailer
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(EmailMessage $message): array
    {
        $payload = [
            'from' => $this->fromName !== ''
                ? sprintf('%s <%s>', $this->fromName, $this->fromEmail)
                : $this->fromEmail,
            'to' => [$message->to],
            'subject' => $message->subject,
            'html' => $message->html,
        ];

        if ($message->text !== '') {
            $payload['text'] = $message->text;
        }

        return $payload;
    }

    public function send(EmailMessage $message): bool
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return false;
        }

        /** @var array<string, mixed> $payload */
        $payload = apply_filters('wlsb/email/payload', $this->payload($message), $message->key, $message);

        $response = wp_remote_post(self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 300;
    }
}
