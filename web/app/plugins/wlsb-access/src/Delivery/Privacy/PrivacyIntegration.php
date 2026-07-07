<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Privacy;

use Wlsb\Access\Domain\Users\UserDirectory;
use Wlsb\Access\Domain\Users\UserMeta;

/**
 * WordPress privacy (GDPR) integration: exports the plugin's per-user lifecycle
 * data and erases verification tokens on a personal-data erasure request.
 */
final class PrivacyIntegration
{
    private const KEY = 'wlsb-access';

    public function __construct(private readonly UserDirectory $users) {}

    public function register(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'registerExporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'registerEraser']);
    }

    /**
     * @param array<string, mixed> $exporters
     * @return array<string, mixed>
     */
    public function registerExporter(array $exporters): array
    {
        $exporters[self::KEY] = [
            'exporter_friendly_name' => __('WLSB Access', 'wlsb-access'),
            'callback' => [$this, 'export'],
        ];

        return $exporters;
    }

    /**
     * @return array{data: list<array<string, mixed>>, done: bool}
     */
    public function export(string $email, int $page = 1): array
    {
        $userId = $this->users->findByEmail($email);
        $data = [];

        if ($userId !== null) {
            $status = $this->users->getStatus($userId);
            $data[] = [
                'group_id' => self::KEY,
                'group_label' => __('Account status', 'wlsb-access'),
                'item_id' => self::KEY . '-' . $userId,
                'data' => [
                    ['name' => __('Status', 'wlsb-access'), 'value' => $status?->value ?? ''],
                    ['name' => __('Requested role', 'wlsb-access'), 'value' => $this->users->getRequestedRole($userId) ?? ''],
                ],
            ];
        }

        return ['data' => $data, 'done' => true];
    }

    /**
     * @param array<string, mixed> $erasers
     * @return array<string, mixed>
     */
    public function registerEraser(array $erasers): array
    {
        $erasers[self::KEY] = [
            'eraser_friendly_name' => __('WLSB Access', 'wlsb-access'),
            'callback' => [$this, 'erase'],
        ];

        return $erasers;
    }

    /**
     * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
     */
    public function erase(string $email, int $page = 1): array
    {
        $userId = $this->users->findByEmail($email);
        $removed = false;

        if ($userId !== null) {
            foreach ([UserMeta::TOKEN_HASH, UserMeta::TOKEN_EXPIRES, UserMeta::TOKEN_CREATED] as $key) {
                $this->users->deleteMeta($userId, $key);
            }

            $removed = true;
        }

        return ['items_removed' => $removed, 'items_retained' => false, 'messages' => [], 'done' => true];
    }
}
