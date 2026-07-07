<?php

declare(strict_types=1);

namespace Wlsb\Access\Application\Notifications;

use Wlsb\Access\Domain\Events\Event;
use Wlsb\Access\Domain\Events\EventLogger;
use Wlsb\Access\Domain\Events\EventType;
use Wlsb\Access\Domain\Mail\EmailMessage;
use Wlsb\Access\Domain\Mail\Mailer;

/**
 * Builds and dispatches the lifecycle emails (verification, welcome, awaiting
 * approval) and records the outcome in the audit log.
 *
 * The audit entry records only the message key and success — never the
 * recipient address — so the log holds no email PII.
 */
final class NotificationService
{
    public function __construct(
        private readonly Mailer $mailer,
        private readonly EventLogger $log,
        private readonly string $siteName,
    ) {}

    public function sendVerification(int $userId, string $toEmail, string $verifyUrl): bool
    {
        $subject = sprintf(
            /* translators: %s: site name */
            __('Verify your email for %s', 'wlsb-access'),
            $this->siteName,
        );

        $intro = __('Thanks for registering. Please confirm your email address to continue:', 'wlsb-access');
        $cta = __('Verify email address', 'wlsb-access');
        $ignore = __('If you did not request this, you can ignore this email.', 'wlsb-access');

        return $this->dispatch($userId, new EmailMessage(
            to: $toEmail,
            subject: $subject,
            html: $this->htmlLayout($intro, $verifyUrl, $cta, $ignore),
            text: $intro . "\n\n" . $verifyUrl . "\n\n" . $ignore,
            key: 'verification',
        ));
    }

    public function sendWelcome(int $userId, string $toEmail, string $loginUrl): bool
    {
        $subject = sprintf(
            /* translators: %s: site name */
            __('Your account at %s is active', 'wlsb-access'),
            $this->siteName,
        );

        $intro = __('Your account is now active. You can sign in here:', 'wlsb-access');
        $cta = __('Sign in', 'wlsb-access');

        return $this->dispatch($userId, new EmailMessage(
            to: $toEmail,
            subject: $subject,
            html: $this->htmlLayout($intro, $loginUrl, $cta, ''),
            text: $intro . "\n\n" . $loginUrl,
            key: 'welcome',
        ));
    }

    public function sendAwaitingApproval(int $userId, string $toEmail): bool
    {
        $subject = sprintf(
            /* translators: %s: site name */
            __('Your registration at %s is awaiting approval', 'wlsb-access'),
            $this->siteName,
        );

        $body = __('Thanks — your email is verified. Your account is now awaiting approval and you will be notified once it is reviewed.', 'wlsb-access');

        return $this->dispatch($userId, new EmailMessage(
            to: $toEmail,
            subject: $subject,
            html: '<p>' . esc_html($body) . '</p>',
            text: $body,
            key: 'awaiting_approval',
        ));
    }

    public function sendApproverNotice(int $approverUserId, string $toEmail, string $reviewUrl): bool
    {
        $subject = sprintf(
            /* translators: %s: site name */
            __('A registration is awaiting your approval at %s', 'wlsb-access'),
            $this->siteName,
        );

        $intro = __('A new registration is awaiting approval. Review it here:', 'wlsb-access');
        $cta = __('Review request', 'wlsb-access');

        return $this->dispatch($approverUserId, new EmailMessage(
            to: $toEmail,
            subject: $subject,
            html: $this->htmlLayout($intro, $reviewUrl, $cta, ''),
            text: $intro . "\n\n" . $reviewUrl,
            key: 'approver_notice',
        ));
    }

    public function sendRejection(int $userId, string $toEmail): bool
    {
        $subject = sprintf(
            /* translators: %s: site name */
            __('Update on your registration at %s', 'wlsb-access'),
            $this->siteName,
        );

        $body = __('Your registration has been reviewed and was not approved.', 'wlsb-access');

        return $this->dispatch($userId, new EmailMessage(
            to: $toEmail,
            subject: $subject,
            html: '<p>' . esc_html($body) . '</p>',
            text: $body,
            key: 'rejected',
        ));
    }

    private function dispatch(int $userId, EmailMessage $message): bool
    {
        $sent = $this->mailer->send($message);

        $this->log->log(new Event(
            $sent ? EventType::NotificationSent : EventType::NotificationFailed,
            'user',
            $userId,
            userId: $userId,
            context: ['key' => $message->key],
        ));

        return $sent;
    }

    private function htmlLayout(string $intro, string $url, string $cta, string $footer): string
    {
        $html = '<p>' . esc_html($intro) . '</p>';
        $html .= '<p><a href="' . esc_url($url) . '" style="display:inline-block;padding:10px 18px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px">'
            . esc_html($cta) . '</a></p>';
        $html .= '<p style="word-break:break-all"><a href="' . esc_url($url) . '">' . esc_html($url) . '</a></p>';

        if ($footer !== '') {
            $html .= '<p style="color:#666;font-size:12px">' . esc_html($footer) . '</p>';
        }

        return $html;
    }
}
