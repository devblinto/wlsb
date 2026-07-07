<?php

/**
 * @var string $state   verified|expired|already|invalid
 * @var string $loginUrl
 * @var string $resendUrl
 */

defined('ABSPATH') || exit;

$loginUrl = $loginUrl ?? '';
$resendUrl = $resendUrl ?? '';
?>
<div class="wlsb-form wlsb-verify">
    <?php if ($state === 'verified') : ?>
        <p class="wlsb-notice wlsb-success"><?php echo esc_html__('Your email address has been verified.', 'wlsb-access'); ?></p>
        <?php if ($loginUrl !== '') : ?>
            <p><a href="<?php echo esc_url($loginUrl); ?>"><?php echo esc_html__('Log in', 'wlsb-access'); ?></a></p>
        <?php endif; ?>
    <?php elseif ($state === 'already') : ?>
        <p class="wlsb-notice"><?php echo esc_html__('This account is already verified.', 'wlsb-access'); ?></p>
        <p><a href="<?php echo esc_url($loginUrl); ?>"><?php echo esc_html__('Log in', 'wlsb-access'); ?></a></p>
    <?php elseif ($state === 'expired') : ?>
        <p class="wlsb-notice wlsb-error"><?php echo esc_html__('This verification link has expired.', 'wlsb-access'); ?></p>
        <p><a href="<?php echo esc_url($resendUrl); ?>"><?php echo esc_html__('Request a new link', 'wlsb-access'); ?></a></p>
    <?php else : ?>
        <p class="wlsb-notice wlsb-error"><?php echo esc_html__('This verification link is invalid.', 'wlsb-access'); ?></p>
        <p><a href="<?php echo esc_url($resendUrl); ?>"><?php echo esc_html__('Request a new link', 'wlsb-access'); ?></a></p>
    <?php endif; ?>
</div>
