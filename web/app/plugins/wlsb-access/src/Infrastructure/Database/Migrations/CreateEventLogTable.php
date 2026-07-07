<?php

declare(strict_types=1);

namespace Wlsb\Access\Infrastructure\Database\Migrations;

use Wlsb\Access\Infrastructure\Database\Migration;

/**
 * Migration 1: the append-only audit/event log.
 *
 * `context` is stored as `longtext` holding JSON (not a native `json` column)
 * to stay friendly to `dbDelta`, which cannot reliably diff generated columns,
 * CHECK constraints, foreign keys, or JSON types. Referential integrity is
 * enforced in the application layer instead.
 */
final class CreateEventLogTable implements Migration
{
    public function __construct(private readonly object $wpdb) {}

    public function version(): int
    {
        return 1;
    }

    public function up(): void
    {
        $table = $this->wpdb->prefix . 'wlsb_event_log';
        $charsetCollate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime(3) NOT NULL,
  event_type varchar(64) NOT NULL,
  object_type varchar(32) NOT NULL,
  object_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned DEFAULT NULL,
  actor_id bigint(20) unsigned DEFAULT NULL,
  actor_ip varbinary(16) DEFAULT NULL,
  created_by_system tinyint(1) NOT NULL DEFAULT 0,
  context longtext DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY object (object_type,object_id,id),
  KEY user_id (user_id,id),
  KEY event_type (event_type,created_at),
  KEY actor_id (actor_id)
) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }
}
