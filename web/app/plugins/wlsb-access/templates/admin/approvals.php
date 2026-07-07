<?php

/**
 * Approval queue.
 *
 * @var list<array{id:int, login:string, email:string, role:string, approver:string}> $rows
 * @var string $notice
 * @var string $formAction
 */

defined('ABSPATH') || exit;

$messages = [
    'approved' => __('Request approved — the account is now active.', 'wlsb-access'),
    'rejected' => __('Request rejected.', 'wlsb-access'),
    'advanced' => __('Step approved — the request moved to the next approver.', 'wlsb-access'),
    'forbidden' => __('You are not permitted to act on that request.', 'wlsb-access'),
    'closed' => __('That request was already decided.', 'wlsb-access'),
];
$noticeClass = in_array($notice, ['forbidden', 'closed'], true) ? 'notice-warning' : 'notice-success';
?>
<div class="wrap">
    <h1><?php echo esc_html__('Approvals', 'wlsb-access'); ?></h1>

    <?php if ($notice !== '' && isset($messages[$notice])) : ?>
        <div class="notice <?php echo esc_attr($noticeClass); ?> is-dismissible"><p><?php echo esc_html($messages[$notice]); ?></p></div>
    <?php endif; ?>

    <?php if ($rows === []) : ?>
        <p><em><?php echo esc_html__('No registrations are awaiting approval.', 'wlsb-access'); ?></em></p>
    <?php else : ?>
        <table class="widefat striped" style="max-width:960px">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Username', 'wlsb-access'); ?></th>
                    <th scope="col"><?php echo esc_html__('Email', 'wlsb-access'); ?></th>
                    <th scope="col"><?php echo esc_html__('Requested role', 'wlsb-access'); ?></th>
                    <th scope="col"><?php echo esc_html__('Current approver', 'wlsb-access'); ?></th>
                    <th scope="col"><?php echo esc_html__('Action', 'wlsb-access'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html($row['login']); ?></td>
                        <td><?php echo esc_html($row['email']); ?></td>
                        <td><code><?php echo esc_html($row['role']); ?></code></td>
                        <td><?php echo esc_html($row['approver']); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url($formAction); ?>" style="display:inline">
                                <input type="hidden" name="action" value="<?php echo esc_attr(\Wlsb\Access\Delivery\Admin\ApprovalQueuePage::ACTION); ?>" />
                                <input type="hidden" name="request_id" value="<?php echo esc_attr((string) $row['id']); ?>" />
                                <?php wp_nonce_field(\Wlsb\Access\Delivery\Admin\ApprovalQueuePage::ACTION); ?>
                                <button type="submit" name="decision" value="approve" class="button button-primary"><?php echo esc_html__('Approve', 'wlsb-access'); ?></button>
                                <button type="submit" name="decision" value="reject" class="button"><?php echo esc_html__('Reject', 'wlsb-access'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
