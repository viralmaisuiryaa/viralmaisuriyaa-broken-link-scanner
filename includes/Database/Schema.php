<?php
/**
 * Database schema.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {
	/**
	 * Install or update database tables.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$scans_table     = self::scans_table();
		$results_table   = self::results_table();

		$sql_scans = "CREATE TABLE {$scans_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'pending',
			total_sources bigint(20) unsigned NOT NULL DEFAULT 0,
			processed_sources bigint(20) unsigned NOT NULL DEFAULT 0,
			total_checks bigint(20) unsigned NOT NULL DEFAULT 0,
			broken_count bigint(20) unsigned NOT NULL DEFAULT 0,
			redirect_count bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime NULL,
			finished_at datetime NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		$sql_results = "CREATE TABLE {$results_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_title text NOT NULL,
			source_url text NOT NULL,
			target_url text NOT NULL,
			final_url text NULL,
			anchor_text text NULL,
			resource_type varchar(20) NOT NULL DEFAULT 'link',
			link_scope varchar(20) NOT NULL DEFAULT 'external',
			status_code smallint(5) unsigned NOT NULL DEFAULT 0,
			result_status varchar(20) NOT NULL DEFAULT 'unknown',
			redirect_count smallint(5) unsigned NOT NULL DEFAULT 0,
			error_message text NULL,
			checked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY source_post_id (source_post_id),
			KEY result_status (result_status),
			KEY status_code (status_code)
		) {$charset_collate};";

		dbDelta( $sql_scans );
		dbDelta( $sql_results );
		update_option( 'vmbls_db_version', VMBLS_VERSION, false );
	}

	/** @return string */
	public static function scans_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vmbls_scans';
	}

	/** @return string */
	public static function results_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vmbls_results';
	}
}
