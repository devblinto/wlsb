<?php

/**
 * Access rules admin screen.
 *
 * @var list<\Wlsb\Access\Domain\Access\AccessRule> $rules
 * @var string $notice
 * @var string $formAction
 */

defined('ABSPATH') || exit;

$kinds = ['any' => __('Any request', 'wlsb-access'), 'url_prefix' => __('URL starts with', 'wlsb-access'), 'post_type' => __('Post type', 'wlsb-access'), 'post_id' => __('Post ID', 'wlsb-access')];
$effects = ['deny' => __('Deny (403)', 'wlsb-access'), 'redirect' => __('Redirect', 'wlsb-access'), 'login' => __('Require login', 'wlsb-access')];
?>
<div class="wrap">
    <h1><?php echo esc_html__('Access rules', 'wlsb-access'); ?></h1>

    <?php if ($notice === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Rules saved.', 'wlsb-access'); ?></p></div>
    <?php endif; ?>

    <p class="description"><?php echo esc_html__('Each rule requires a capability for the matched requests; users without it get the chosen effect. Rules never grant access. For a single page, use the "Access control" box on the page editor instead.', 'wlsb-access'); ?></p>

    <form method="post" action="<?php echo esc_url($formAction); ?>">
        <input type="hidden" name="action" value="<?php echo esc_attr(\Wlsb\Access\Delivery\Admin\AccessRulesPage::ACTION); ?>" />
        <?php wp_nonce_field(\Wlsb\Access\Delivery\Admin\AccessRulesPage::ACTION); ?>

        <h2><?php echo esc_html__('Current rules', 'wlsb-access'); ?></h2>
        <?php if ($rules === []) : ?>
            <p><em><?php echo esc_html__('No rules yet.', 'wlsb-access'); ?></em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:960px">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Match', 'wlsb-access'); ?></th>
                        <th><?php echo esc_html__('Requires capability', 'wlsb-access'); ?></th>
                        <th><?php echo esc_html__('Effect', 'wlsb-access'); ?></th>
                        <th><?php echo esc_html__('Priority', 'wlsb-access'); ?></th>
                        <th style="text-align:center"><?php echo esc_html__('Delete', 'wlsb-access'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $rule) : ?>
                        <tr>
                            <td><?php echo esc_html(($kinds[$rule->matcherKind->value] ?? $rule->matcherKind->value) . ($rule->matcherValue !== '' ? ': ' . $rule->matcherValue : '')); ?></td>
                            <td><code><?php echo esc_html($rule->requiredCapability !== '' ? $rule->requiredCapability : '—'); ?></code></td>
                            <td><?php echo esc_html($effects[$rule->effect->value] ?? $rule->effect->value); ?><?php echo $rule->redirectTarget !== null ? ' → ' . esc_html($rule->redirectTarget) : ''; ?></td>
                            <td><?php echo esc_html((string) $rule->priority); ?></td>
                            <td style="text-align:center"><input type="checkbox" name="delete[]" value="<?php echo esc_attr($rule->id); ?>" /></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2><?php echo esc_html__('Add a rule', 'wlsb-access'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Match', 'wlsb-access'); ?></th>
                <td>
                    <select name="new_kind">
                        <?php foreach ($kinds as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="new_value" placeholder="/members  ·  page  ·  42" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Requires capability', 'wlsb-access'); ?></th>
                <td><input type="text" name="new_capability" placeholder="wlsb_view_content" class="regular-text" /></td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Effect', 'wlsb-access'); ?></th>
                <td>
                    <select name="new_effect">
                        <?php foreach ($effects as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="new_redirect" placeholder="<?php echo esc_attr__('Redirect URL (optional)', 'wlsb-access'); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Priority', 'wlsb-access'); ?></th>
                <td><input type="number" name="new_priority" value="10" min="0" /></td>
            </tr>
        </table>

        <?php submit_button(__('Save rules', 'wlsb-access')); ?>
    </form>
</div>
