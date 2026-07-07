<?php

/**
 * Registration workflow config.
 *
 * @var \Wlsb\Access\Domain\Workflow\WorkflowConfig $config
 * @var array<string, string>                       $roles  slug => display name
 * @var string                                      $notice
 * @var string                                      $formAction
 */

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html__('Registration workflow', 'wlsb-access'); ?></h1>

    <?php if ($notice === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Workflow saved.', 'wlsb-access'); ?></p></div>
    <?php endif; ?>

    <p class="description"><?php echo esc_html__('Choose which roles users may self-register as, and whether registration requires approval.', 'wlsb-access'); ?></p>

    <form method="post" action="<?php echo esc_url($formAction); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(\Wlsb\Access\Delivery\Admin\WorkflowConfigPage::ACTION); ?>" />
        <?php wp_nonce_field(\Wlsb\Access\Delivery\Admin\WorkflowConfigPage::ACTION); ?>

        <table class="widefat striped" style="max-width:900px">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Role', 'wlsb-access'); ?></th>
                    <th scope="col" style="text-align:center"><?php echo esc_html__('Registerable', 'wlsb-access'); ?></th>
                    <th scope="col" style="text-align:center"><?php echo esc_html__('Self-selectable', 'wlsb-access'); ?></th>
                    <th scope="col" style="text-align:center"><?php echo esc_html__('Requires approval', 'wlsb-access'); ?></th>
                    <th scope="col"><?php echo esc_html__('Approved by', 'wlsb-access'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $slug => $name) : ?>
                    <?php
                    $workflow = $config->forRole($slug);
                    $approverRole = 'administrator';
                    if ($workflow !== null && isset($workflow->steps[0]['approver_role'])) {
                        $approverRole = (string) $workflow->steps[0]['approver_role'];
                    }
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($name); ?></strong> <code><?php echo esc_html($slug); ?></code></td>
                        <td style="text-align:center"><input type="checkbox" name="registerable[<?php echo esc_attr($slug); ?>]" value="1" <?php checked($workflow?->registerable ?? false); ?> /></td>
                        <td style="text-align:center"><input type="checkbox" name="self_selectable[<?php echo esc_attr($slug); ?>]" value="1" <?php checked($workflow?->selfSelectable ?? false); ?> /></td>
                        <td style="text-align:center"><input type="checkbox" name="requires_approval[<?php echo esc_attr($slug); ?>]" value="1" <?php checked($workflow?->requiresApproval ?? false); ?> /></td>
                        <td>
                            <select name="approver[<?php echo esc_attr($slug); ?>]">
                                <?php foreach ($roles as $optSlug => $optName) : ?>
                                    <option value="<?php echo esc_attr($optSlug); ?>" <?php selected($approverRole, $optSlug); ?>><?php echo esc_html($optName); ?></option>
                                <?php endforeach; ?>
                                <option value="administrator" <?php selected($approverRole, 'administrator'); ?>><?php echo esc_html__('Administrator', 'wlsb-access'); ?></option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php submit_button(__('Save workflow', 'wlsb-access')); ?>
    </form>
</div>
