<?php
/**
 * Scan repository.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository {
	private const CACHE_GROUP = 'linkpulse';

	/**
	 * Create scan.
	 *
	 * @param int $total Total source items.
	 * @return int
	 */
	public function create_scan( int $total ): int {
		global $wpdb;

		// Custom plugin tables require direct repository writes.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::scans_table(),
			array(
				'status'        => 'running',
				'total_sources' => $total,
				'started_at'    => current_time( 'mysql' ),
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%d', '%s' )
		);

		$this->flush_scan_cache();
		return (int) $wpdb->insert_id;
	}

	/**
	 * Add result.
	 *
	 * @param array<string,mixed> $data Result data.
	 * @return void
	 */
	public function add_result( array $data ): void {
		global $wpdb;

		// Custom plugin tables require direct repository writes.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Schema::results_table(),
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		wp_cache_delete( 'results_' . (int) $data['scan_id'], self::CACHE_GROUP );
	}

	/**
	 * Increment scan counters.
	 *
	 * @param int $scan_id Scan ID.
	 * @param int $sources Sources processed.
	 * @param int $checks URL checks performed.
	 * @param int $broken Broken checks.
	 * @param int $redirects Redirect checks.
	 * @return void
	 */
	public function increment( int $scan_id, int $sources, int $checks, int $broken, int $redirects ): void {
		global $wpdb;
		$table = Schema::scans_table();

		$sql = $wpdb->prepare(
			'UPDATE %i
			 SET processed_sources = processed_sources + %d,
			     total_checks = total_checks + %d,
			     broken_count = broken_count + %d,
			     redirect_count = redirect_count + %d
			 WHERE id = %d',
			$table,
			$sources,
			$checks,
			$broken,
			$redirects,
			$scan_id
		);
		$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$this->flush_scan_cache( $scan_id );
	}

	/**
	 * Finish scan.
	 *
	 * @param int $scan_id Scan ID.
	 * @return void
	 */
	public function finish( int $scan_id ): void {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			Schema::scans_table(),
			array(
				'status'      => 'completed',
				'finished_at' => current_time( 'mysql' ),
			),
			array( 'id' => $scan_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$this->flush_scan_cache( $scan_id );
	}

	/**
	 * Get scan.
	 *
	 * @param int $scan_id Scan ID.
	 * @return object|null
	 */
	public function get_scan( int $scan_id ) {
		$cache_key = 'scan_' . $scan_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		global $wpdb;
		$sql  = $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Schema::scans_table(), $scan_id );
		$scan = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		wp_cache_set( $cache_key, $scan ?: 0, self::CACHE_GROUP, 60 );
		return $scan;
	}

	/**
	 * Get latest scan.
	 *
	 * @return object|null
	 */
	public function latest_scan() {
		$cached = wp_cache_get( 'latest_scan', self::CACHE_GROUP );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		global $wpdb;
		$sql  = $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', Schema::scans_table() );
		$scan = $wpdb->get_row( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		wp_cache_set( 'latest_scan', $scan ?: 0, self::CACHE_GROUP, 60 );
		return $scan;
	}


	/**
	 * Get recent scans.
	 *
	 * @param int $limit Maximum number of scans.
	 * @return array<int,object>
	 */
	public function get_scans( int $limit = 20 ): array {
		global $wpdb;
		$limit     = min( 100, max( 1, $limit ) );
		$cache_key = 'scans_' . $limit;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		$sql   = $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d', Schema::scans_table(), $limit );
		$scans = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		wp_cache_set( $cache_key, $scans, self::CACHE_GROUP, 60 );
		return is_array( $scans ) ? $scans : array();
	}


	/**
	 * Get results.
	 *
	 * @param int    $scan_id Scan ID.
	 * @param string $filter Filter.
	 * @param int    $limit Limit.
	 * @return array<int,object>
	 */
	public function get_results( int $scan_id, string $filter = 'all', int $limit = 500 ): array {
		global $wpdb;

		$allowed_filters = array( 'all', 'broken', 'redirect', 'ok', 'internal', 'external', 'link', 'image' );
		$filter          = in_array( $filter, $allowed_filters, true ) ? $filter : 'all';
		$limit           = min( 100000, max( 1, $limit ) );
		$cache_key       = 'results_' . $scan_id . '_' . $filter . '_' . $limit;
		$cached          = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : array();
		}

		if ( 'broken' === $filter || 'redirect' === $filter || 'ok' === $filter ) {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					'SELECT * FROM %i WHERE scan_id = %d AND result_status = %s ORDER BY id DESC LIMIT %d',
					Schema::results_table(),
					$scan_id,
					$filter,
					$limit
				)
			);
		} elseif ( 'internal' === $filter || 'external' === $filter ) {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					'SELECT * FROM %i WHERE scan_id = %d AND link_scope = %s ORDER BY id DESC LIMIT %d',
					Schema::results_table(),
					$scan_id,
					$filter,
					$limit
				)
			);
		} elseif ( 'link' === $filter || 'image' === $filter ) {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					'SELECT * FROM %i WHERE scan_id = %d AND resource_type = %s ORDER BY id DESC LIMIT %d',
					Schema::results_table(),
					$scan_id,
					$filter,
					$limit
				)
			);
		} else {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					'SELECT * FROM %i WHERE scan_id = %d ORDER BY id DESC LIMIT %d',
					Schema::results_table(),
					$scan_id,
					$limit
				)
			);
		}

		wp_cache_set( $cache_key, $results, self::CACHE_GROUP, 60 );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Flush scan caches.
	 *
	 * @param int $scan_id Optional scan ID.
	 * @return void
	 */
	private function flush_scan_cache( int $scan_id = 0 ): void {
		wp_cache_delete( 'latest_scan', self::CACHE_GROUP );
		wp_cache_delete( 'scans_20', self::CACHE_GROUP );
		if ( $scan_id > 0 ) {
			wp_cache_delete( 'scan_' . $scan_id, self::CACHE_GROUP );
		}
	}
}
