<?php

/**
 * @var string $error
 * @var string $nonce
 * @var string $registerUrl
 * @var string $resendUrl
 */

defined('ABSPATH') || exit;
?>
<div class="wlsb-form wlsb-login">
    <?php if (! empty($error)) : ?>
        <p class="wlsb-notice wlsb-error"><?php echo esc_html($error); ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="wlsb_action" value="login" />
        <input type="hidden" name="_wlsb_nonce" value="<?php echo esc_attr($nonce); ?>" />
        <p>
            <label><?php echo esc_html__('Username or email', 'wlsb-access'); ?><br />
                <input type="text" name="wlsb_login" autocomplete="username" required />
            </label>
        </p>
        <p>
            <label><?php echo esc_html__('Password', 'wlsb-access'); ?><br />
                <input type="password" name="wlsb_password" autocomplete="current-password" required />
            </label>
        </p>
        <p>
            <label><input type="checkbox" name="wlsb_remember" value="1" /> <?php echo esc_html__('Remember me', 'wlsb-access'); ?></label>
        </p>
        <p><button type="submit"><?php echo esc_html__('Log in', 'wlsb-access'); ?></button></p>
    </form>
    <p>
        <a href="<?php echo esc_url($registerUrl); ?>"><?php echo esc_html__('Create an account', 'wlsb-access'); ?></a>
        &nbsp;·&nbsp;
        <a href="<?php echo esc_url($resendUrl); ?>"><?php echo esc_html__('Resend verification email', 'wlsb-access'); ?></a>
    </p>
</div>
