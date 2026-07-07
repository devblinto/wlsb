<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Frontend;

use Wlsb\Access\Application\Registration\EmailVerificationService;
use Wlsb\Access\Application\Registration\RegistrationException;
use Wlsb\Access\Application\Registration\RegistrationInput;
use Wlsb\Access\Application\Registration\RegistrationService;
use Wlsb\Access\Application\Registration\VerificationResult;
use Wlsb\Access\Domain\Users\UserDirectory;
use Wlsb\Access\Domain\Workflow\WorkflowConfigStore;
use Wlsb\Access\Infrastructure\Pages\PageProvisioner;

/**
 * Server-rendered registration/login/verification shortcodes.
 *
 * Forms self-submit and are CSRF-protected with nonces; the verification link is
 * authenticated by its single-use token (no nonce). Decisions live in the
 * unit-tested application services; this controller only parses/sanitises input,
 * renders escaped templates, and redirects.
 */
final class RegistrationShortcodes
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly EmailVerificationService $verification,
        private readonly WorkflowConfigStore $workflow,
        private readonly UserDirectory $users,
        private readonly PageProvisioner $pages,
    ) {}

    public function register(): void
    {
        add_shortcode('wlsb_register', [$this, 'renderRegister']);
        add_shortcode('wlsb_login', [$this, 'renderLogin']);
        add_shortcode('wlsb_verify_email', [$this, 'renderVerify']);
        add_shortcode('wlsb_resend_verification', [$this, 'renderResend']);
        add_shortcode('wlsb_registration_status', [$this, 'renderStatus']);
    }

    public function renderRegister(): string
    {
        if (is_user_logged_in()) {
            return $this->view('logged-in');
        }

        $error = '';
        $done = false;
        $roles = $this->workflow->load()->selfSelectableRoles();

        if ($this->isSubmit('register')) {
            try {
                $this->registration->register(new RegistrationInput(
                    $this->postEmail('wlsb_email'),
                    $this->post('wlsb_login'),
                    $this->postRaw('wlsb_password'),
                    $this->post('wlsb_role'),
                ));
                $done = true;
            } catch (RegistrationException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->view('register', [
            'error' => $error,
            'done' => $done,
            'roles' => $this->roleChoices($roles),
            'nonce' => wp_create_nonce('wlsb_register'),
            'loginUrl' => $this->pages->url('login'),
        ]);
    }

    public function renderLogin(): string
    {
        if (is_user_logged_in()) {
            return $this->view('logged-in');
        }

        $error = '';

        if ($this->isSubmit('login')) {
            $user = wp_signon([
                'user_login' => $this->post('wlsb_login'),
                'user_password' => $this->postRaw('wlsb_password'),
                'remember' => $this->post('wlsb_remember') === '1',
            ], is_ssl());

            if (is_wp_error($user)) {
                $error = $user->get_error_message();
            } else {
                wp_safe_redirect(home_url('/'));
                exit;
            }
        }

        return $this->view('login', [
            'error' => $error,
            'nonce' => wp_create_nonce('wlsb_login'),
            'registerUrl' => $this->pages->url('register'),
            'resendUrl' => $this->pages->url('resend'),
        ]);
    }

    public function renderVerify(): string
    {
        // The single-use token in the link is the credential; no nonce applies.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $userId = isset($_GET['wlsb_uid']) ? absint(wp_unslash($_GET['wlsb_uid'])) : 0;
        $token = isset($_GET['wlsb_token']) ? sanitize_text_field(wp_unslash($_GET['wlsb_token'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($userId <= 0 || $token === '') {
            return $this->view('verify', ['state' => 'invalid', 'resendUrl' => $this->pages->url('resend')]);
        }

        $state = match ($this->verification->verify($userId, $token)) {
            VerificationResult::Verified => 'verified',
            VerificationResult::Expired => 'expired',
            VerificationResult::AlreadyVerified => 'already',
            VerificationResult::Invalid => 'invalid',
        };

        return $this->view('verify', [
            'state' => $state,
            'loginUrl' => $this->pages->url('login'),
            'resendUrl' => $this->pages->url('resend'),
        ]);
    }

    public function renderResend(): string
    {
        $done = false;

        if ($this->isSubmit('resend')) {
            $email = $this->postEmail('wlsb_email');
            $userId = $email !== '' ? $this->users->findByEmail($email) : null;

            if ($userId !== null) {
                $this->verification->resend($userId);
            }

            $done = true; // always generic — never reveals whether the email exists
        }

        return $this->view('resend', [
            'done' => $done,
            'nonce' => wp_create_nonce('wlsb_resend'),
        ]);
    }

    public function renderStatus(): string
    {
        if (! is_user_logged_in()) {
            return $this->view('status', [
                'state' => 'guest',
                'loginUrl' => $this->pages->url('login'),
                'registerUrl' => $this->pages->url('register'),
            ]);
        }

        $status = $this->users->getStatus(get_current_user_id());

        return $this->view('status', [
            'state' => 'known',
            'status' => $status?->value ?? 'active',
        ]);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function view(string $template, array $vars = []): string
    {
        return (function () use ($template, $vars): string {
            extract($vars, EXTR_SKIP);
            ob_start();
            include dirname(__DIR__, 3) . "/templates/frontend/{$template}.php";

            return (string) ob_get_clean();
        })();
    }

    private function isSubmit(string $action): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $submitted = isset($_POST['wlsb_action']) ? sanitize_key(wp_unslash($_POST['wlsb_action'])) : '';

        if ($submitted !== $action) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce = isset($_POST['_wlsb_nonce']) ? sanitize_text_field(wp_unslash($_POST['_wlsb_nonce'])) : '';

        return wp_verify_nonce($nonce, 'wlsb_' . $action) !== false;
    }

    private function post(string $key): string
    {
        // Nonce verified in isSubmit() before any handler reads input.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
    }

    private function postEmail(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return isset($_POST[$key]) ? sanitize_email(wp_unslash($_POST[$key])) : '';
    }

    private function postRaw(string $key): string
    {
        // Passwords must not be sanitised (that would change them); they are never
        // output and go straight to WordPress hashing. Nonce verified in isSubmit().
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        return isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';
    }

    /**
     * @param list<string> $roles
     * @return array<string, string> slug => label
     */
    private function roleChoices(array $roles): array
    {
        $names = wp_roles()->role_names;
        $choices = [];

        foreach ($roles as $slug) {
            $choices[$slug] = isset($names[$slug]) ? (string) translate_user_role($names[$slug]) : $slug;
        }

        return $choices;
    }
}
