<?php
/**
 * CSV exporter.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Admin;

use ViralmaisuriyaaBrokenLinkScanner\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Csv_Exporter {
	/** @return void */
	public function register(): void {
		add_action( 'admin_post_vmbls_export_csv', array( $this, 'export' ) );
	}

	/** @return void */
	public function export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'viralmaisuriyaa-broken-link-scanner' ) );
		}

		$scan_id = isset( $_GET['scan_id'] ) ? absint( $_GET['scan_id'] ) : 0;
		check_admin_referer( 'vmbls_export_' . $scan_id );

		if ( ! $scan_id ) {
			wp_die( esc_html__( 'Invalid scan ID.', 'viralmaisuriyaa-broken-link-scanner' ) );
		}

		$repository = new Repository();
		$results    = $repository->get_results( $scan_id, 'all', 100000 );
		$filename   = 'broken-link-checker-scan-' . $scan_id . '-' . gmdate( 'Y-m-d-His' ) . '.csv';
		$rows       = array(
			array( 'Source Page', 'Source URL', 'Anchor / Alt Text', 'Target URL', 'Final URL', 'Resource Type', 'Scope', 'HTTP Status', 'Result', 'Redirect Count', 'Error', 'Checked At' ),
		);

		foreach ( $results as $row ) {
			$rows[] = array(
				$row->source_title,
				$row->source_url,
				$row->anchor_text,
				$row->target_url,
				$row->final_url,
				$row->resource_type,
				$row->link_scope,
				$row->status_code,
				$row->result_status,
				$row->redirect_count,
				$row->error_message,
				$row->checked_at,
			);
		}

		$csv = "\xEF\xBB\xBF";
		foreach ( $rows as $row ) {
			$csv .= $this->csv_line( $row );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( $csv ) );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV download, values are safely quoted by csv_line().
		exit;
	}

	/**
	 * Convert values to one RFC 4180-compatible CSV line.
	 *
	 * @param array<int,mixed> $values Row values.
	 * @return string
	 */
	private function csv_line( array $values ): string {
		$cells = array_map(
			static function ( $value ): string {
				$value = (string) $value;
				// Prevent spreadsheet formula injection without changing normal URLs/text.
				if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) {
					$value = "'" . $value;
				}
				return '"' . str_replace( '"', '""', $value ) . '"';
			},
			$values
		);

		return implode( ',', $cells ) . "\r\n";
	}
}
