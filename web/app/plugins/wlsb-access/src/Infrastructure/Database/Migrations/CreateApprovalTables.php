<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Database\Migrations;

use Wlsb\Access\Infrastructure\Database\Migration;

/**
 * Migration 2: the approval request + step tables.
 *
 * Chain-capable (one row per step) even though v1 uses a single step. Statuses
 * are VARCHAR (not native ENUM) so new values need no ALTER. "At most one open
 * request per user" is enforced in the application layer rather than via a
 * generated column + unique index, because dbDelta cannot create those.
 */
final class CreateApprovalTables implements Migration
{
    public function __construct(private readonly object $wpdb) {}

    public function version(): int
    {
        return 2;
    }

    public function up(): void
    {
        $prefix = $this->wpdb->prefix;
        $charsetCollate = $this->wpdb->get_charset_collate();

        $requests = "CREATE TABLE {$prefix}wlsb_approval_requests (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  requested_role varchar(191) NOT NULL,
  workflow_key varchar(191) NOT NULL DEFAULT '',
  workflow_version int(10) unsigned NOT NULL DEFAULT 1,
  status varchar(20) NOT NULL DEFAULT 'pending',
  current_step_order smallint(5) unsigned NOT NULL DEFAULT 1,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  decided_at datetime DEFAULT NULL,
  decided_by bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY status (status,current_step_order),
  KEY decided_by (decided_by)
) {$charsetCollate};";

        $steps = "CREATE TABLE {$prefix}wlsb_approval_steps (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  request_id bigint(20) unsigned NOT NULL,
  step_order smallint(5) unsigned NOT NULL,
  approver_type varchar(10) NOT NULL,
  approver_role varchar(191) DEFAULT NULL,
  approver_user_id bigint(20) unsigned DEFAULT NULL,
  status varchar(10) NOT NULL DEFAULT 'pending',
  acted_by bigint(20) unsigned DEFAULT NULL,
  acted_at datetime DEFAULT NULL,
  note text DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY request_step (request_id,step_order),
  KEY approver_role (approver_role),
  KEY approver_user (approver_user_id)
) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($requests);
        dbDelta($steps);
    }
}
