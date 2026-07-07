<?php

/**
 * Access management — roles × capabilities matrix.
 *
 * Rendered by AccessMatrixPage::render(); all variables are prepared and
 * escaped-on-output here.
 *
 * @var array<string, string>                                        $roles
 * @var array<string, array<string, bool>>                           $checked
 * @var array<string, true>                                          $protectedPairs
 * @var list<\Wlsb\Access\Domain\Capabilities\CapabilityGroup>       $groups
 * @var \Wlsb\Access\Domain\Capabilities\CapabilityRegistry          $capabilities
 * @var string                                                       $notice
 * @var string                                                       $formAction
 */

defined('ABSPATH') || exit;

$columnSpan = count($roles) + 1;
?>
<div class="wrap">
    <h1><?php echo esc_html__('Access management', 'wlsb-access'); ?></h1>

    <?php if ($notice === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Access settings saved.', 'wlsb-access'); ?></p></div>
    <?php elseif ($notice === 'escalation') : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html__('You cannot grant a capability that you do not hold yourself.', 'wlsb-access'); ?></p></div>
    <?php elseif ($notice === 'lockout') : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html__('You cannot remove your own management capability.', 'wlsb-access'); ?></p></div>
    <?php endif; ?>

    <p class="description">
        <?php echo esc_html__('Choose which roles hold each capability. Protected capabilities are always granted and cannot be removed here.', 'wlsb-access'); ?>
    </p>

    <form method="post" action="<?php echo esc_url($formAction); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(\Wlsb\Access\Delivery\Admin\AccessMatrixPage::ACTION); ?>" />
        <?php wp_nonce_field(\Wlsb\Access\Delivery\Admin\AccessMatrixPage::ACTION); ?>

        <table class="widefat striped" style="max-width:960px">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Capability', 'wlsb-access'); ?></th>
                    <?php foreach ($roles as $roleName) : ?>
                        <th scope="col" style="text-align:center"><?php echo esc_html($roleName); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $group) : ?>
                    <tr>
                        <th colspan="<?php echo esc_attr((string) $columnSpan); ?>"><?php echo esc_html($group->label); ?></th>
                    </tr>
                    <?php foreach ($capabilities->capabilitiesInGroup($group->key) as $capability) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($capability->label); ?></strong>
                                <?php if ($capability->description !== '') : ?>
                                    <br /><span class="description"><?php echo esc_html($capability->description); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($roles as $roleSlug => $roleName) : ?>
                                <?php
                                $boxName = sprintf('caps[%s][%s]', $roleSlug, $capability->key);
                                $isChecked = $checked[$roleSlug][$capability->key] ?? false;
                                $isProtected = isset($protectedPairs[$roleSlug . '|' . $capability->key]);
                                ?>
                                <td style="text-align:center">
                                    <input
                                        type="checkbox"
                                        name="<?php echo esc_attr($boxName); ?>"
                                        value="1"
                                        <?php checked($isChecked); ?>
                                        <?php disabled($isProtected); ?>
                                    />
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button(__('Save access settings', 'wlsb-access')); ?>
    </form>
</div>
