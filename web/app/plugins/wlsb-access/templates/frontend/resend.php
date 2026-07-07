<?php

/**
 * @var bool   $done
 * @var string $nonce
 */

defined('ABSPATH') || exit;
?>
<div class="wlsb-form wlsb-resend">
    <?php if (! empty($done)) : ?>
        <p class="wlsb-notice wlsb-success">
            <?php echo esc_html__('If that email is registered and not yet verified, a new verification link is on its way.', 'wlsb-access'); ?>
        </p>
    <?php else : ?>
        <p><?php echo esc_html__('Enter your email address to receive a new verification link.', 'wlsb-access'); ?></p>
        <form method="post">
            <input type="hidden" name="wlsb_action" value="resend" />
            <input type="hidden" name="_wlsb_nonce" value="<?php echo esc_attr($nonce); ?>" />
            <p>
                <label><?php echo esc_html__('Email', 'wlsb-access'); ?><br />
                    <input type="email" name="wlsb_email" autocomplete="email" required />
                </label>
            </p>
            <p><button type="submit"><?php echo esc_html__('Send verification link', 'wlsb-access'); ?></button></p>
        </form>
    <?php endif; ?>
</div>
