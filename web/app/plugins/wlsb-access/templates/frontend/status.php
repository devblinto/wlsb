<?php

/**
 * @var string $state  guest|known
 * @var string $status lifecycle status value (when known)
 * @var string $loginUrl
 * @var string $registerUrl
 */

defined('ABSPATH') || exit;

$status = $status ?? 'active';
$loginUrl = $loginUrl ?? '';
$registerUrl = $registerUrl ?? '';

$labels = [
    'pending_email_verification' => __('Your email address is not verified yet.', 'wlsb-access'),
    'pending_approval' => __('Your account is awaiting approval.', 'wlsb-access'),
    'active' => __('Your account is active.', 'wlsb-access'),
    'rejected' => __('Your registration was not approved.', 'wlsb-access'),
    'suspended' => __('Your account has been suspended.', 'wlsb-access'),
];
?>
<div class="wlsb-form wlsb-status">
    <?php if ($state === 'guest') : ?>
        <p><?php echo esc_html__('You are not signed in.', 'wlsb-access'); ?></p>
        <p>
            <a href="<?php echo esc_url($loginUrl); ?>"><?php echo esc_html__('Log in', 'wlsb-access'); ?></a>
            &nbsp;·&nbsp;
            <a href="<?php echo esc_url($registerUrl); ?>"><?php echo esc_html__('Register', 'wlsb-access'); ?></a>
        </p>
    <?php else : ?>
        <p class="wlsb-notice"><?php echo esc_html($labels[$status] ?? $labels['active']); ?></p>
    <?php endif; ?>
</div>
