<?php

/**
 * @var string                 $error
 * @var bool                   $done
 * @var array<string, string>  $roles slug => label
 * @var string                 $nonce
 * @var string                 $loginUrl
 */

defined('ABSPATH') || exit;
?>
<div class="wlsb-form wlsb-register">
    <?php if (! empty($done)) : ?>
        <p class="wlsb-notice wlsb-success">
            <?php echo esc_html__('Thanks! Please check your email for a verification link to activate your account.', 'wlsb-access'); ?>
        </p>
    <?php elseif (empty($roles)) : ?>
        <p class="wlsb-notice"><?php echo esc_html__('Registration is currently closed.', 'wlsb-access'); ?></p>
    <?php else : ?>
        <?php if (! empty($error)) : ?>
            <p class="wlsb-notice wlsb-error"><?php echo esc_html($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="wlsb_action" value="register" />
            <input type="hidden" name="_wlsb_nonce" value="<?php echo esc_attr($nonce); ?>" />
            <p>
                <label><?php echo esc_html__('Username', 'wlsb-access'); ?><br />
                    <input type="text" name="wlsb_login" autocomplete="username" required />
                </label>
            </p>
            <p>
                <label><?php echo esc_html__('Email', 'wlsb-access'); ?><br />
                    <input type="email" name="wlsb_email" autocomplete="email" required />
                </label>
            </p>
            <p>
                <label><?php echo esc_html__('Password', 'wlsb-access'); ?><br />
                    <input type="password" name="wlsb_password" autocomplete="new-password" required />
                </label>
            </p>
            <?php if (count($roles) > 1) : ?>
                <p>
                    <label><?php echo esc_html__('Register as', 'wlsb-access'); ?><br />
                        <select name="wlsb_role">
                            <?php foreach ($roles as $slug => $label) : ?>
                                <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </p>
            <?php else : ?>
                <input type="hidden" name="wlsb_role" value="<?php echo esc_attr((string) array_key_first($roles)); ?>" />
            <?php endif; ?>
            <p><button type="submit"><?php echo esc_html__('Create account', 'wlsb-access'); ?></button></p>
        </form>
        <p><a href="<?php echo esc_url($loginUrl); ?>"><?php echo esc_html__('Already have an account? Log in', 'wlsb-access'); ?></a></p>
    <?php endif; ?>
</div>
