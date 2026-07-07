<?php

/**
 * Access management — custom roles (create / rename / delete).
 *
 * Rendered by RolesPage::render(); all variables are prepared and escaped here.
 *
 * @var list<\Wlsb\Access\Domain\Roles\RoleBlueprint> $customRoles
 * @var string                                        $notice
 * @var string                                        $errorMessage
 * @var string                                        $formAction
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html__('Roles', 'wlsb-access'); ?></h1>

    <?php if ($notice === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Roles updated.', 'wlsb-access'); ?></p></div>
    <?php endif; ?>
    <?php if ($errorMessage !== '') : ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($errorMessage); ?></p></div>
    <?php endif; ?>

    <p class="description">
        <?php echo esc_html__('Create custom roles here, then grant them capabilities on the Access screen. Only custom roles can be renamed or deleted; core roles are left untouched.', 'wlsb-access'); ?>
    </p>

    <form method="post" action="<?php echo esc_url($formAction); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(\Wlsb\Access\Delivery\Admin\RolesPage::ACTION); ?>" />
        <?php wp_nonce_field(\Wlsb\Access\Delivery\Admin\RolesPage::ACTION); ?>

        <h2><?php echo esc_html__('Custom roles', 'wlsb-access'); ?></h2>
        <?php if ($customRoles === []) : ?>
            <p><em><?php echo esc_html__('No custom roles yet.', 'wlsb-access'); ?></em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:720px">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Slug', 'wlsb-access'); ?></th>
                        <th scope="col"><?php echo esc_html__('Display name', 'wlsb-access'); ?></th>
                        <th scope="col" style="text-align:center"><?php echo esc_html__('Delete', 'wlsb-access'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customRoles as $role) : ?>
                        <tr>
                            <td><code><?php echo esc_html($role->slug); ?></code></td>
                            <td>
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="role_name[<?php echo esc_attr($role->slug); ?>]"
                                    value="<?php echo esc_attr($role->displayName); ?>"
                                />
                            </td>
                            <td style="text-align:center">
                                <input type="checkbox" name="delete[]" value="<?php echo esc_attr($role->slug); ?>" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2><?php echo esc_html__('Add a role', 'wlsb-access'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="new_role_slug"><?php echo esc_html__('Slug', 'wlsb-access'); ?></label></th>
                <td>
                    <input type="text" id="new_role_slug" name="new_role_slug" class="regular-text" placeholder="wlsb_vendor" />
                    <p class="description"><?php echo esc_html__('Lowercase letters, numbers, hyphens and underscores.', 'wlsb-access'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="new_role_name"><?php echo esc_html__('Display name', 'wlsb-access'); ?></label></th>
                <td><input type="text" id="new_role_name" name="new_role_name" class="regular-text" placeholder="Vendor" /></td>
            </tr>
        </table>

        <?php submit_button(__('Save roles', 'wlsb-access')); ?>
    </form>
</div>
