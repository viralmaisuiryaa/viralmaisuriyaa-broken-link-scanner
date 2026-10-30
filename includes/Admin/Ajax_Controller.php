<?php
/**
 * AJAX scan controller.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Admin;

use ViralmaisuriyaaBrokenLinkScanner\Database\Repository;
use ViralmaisuriyaaBrokenLinkScanner\Scanner\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ajax_Controller {
	/** @return void */
	public function register(): void {
		add_action( 'wp_ajax_vmbls_start_scan', array( $this, 'start_scan' ) );
		add_action( 'wp_ajax_vmbls_scan_batch', array( $this, 'scan_batch' ) );
	}

	/** @return void */
	public function start_scan(): void {
		$this->authorize();

		$scanner    = new Scanner();
		$total      = $scanner->count_sources();
		$repository = new Repository();
		$scan_id    = $repository->create_scan( $total );

		if ( 0 === $total ) {
			$repository->finish( $scan_id );
		}

		wp_send_json_success(
			array(
				'scanId' => $scan_id,
				'total'  => $total,
				'done'   => 0 === $total,
			)
		);
	}

	/** @return void */
	public function scan_batch(): void {
		$this->authorize();

		$scan_id  = filter_input( INPUT_POST, 'scan_id', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$page     = filter_input( INPUT_POST, 'batch', FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		$scan_id  = $scan_id ? absint( $scan_id ) : 0;
		$page     = $page ? absint( $page ) : 1;
		$per_page = min( 10, max( 1, absint( get_option( 'vmbls_batch_size', 2 ) ) ) );

		if ( ! $scan_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid scan ID.', 'viralmaisuriyaa-broken-link-scanner' ) ), 400 );
		}

		$repository = new Repository();
		$scan       = $repository->get_scan( $scan_id );
		if ( ! $scan || 'running' !== $scan->status ) {
			wp_send_json_error( array( 'message' => __( 'This scan is not running.', 'viralmaisuriyaa-broken-link-scanner' ) ), 409 );
		}

		$scanner = new Scanner();
		$scanner->scan_batch( $scan_id, $page, $per_page );
		$scan = $repository->get_scan( $scan_id );

		wp_send_json_success(
			array(
				'scanId'    => $scan_id,
				'total'     => (int) $scan->total_sources,
				'processed' => (int) $scan->processed_sources,
				'checks'    => (int) $scan->total_checks,
				'broken'    => (int) $scan->broken_count,
				'redirects' => (int) $scan->redirect_count,
				'done'      => 'completed' === $scan->status,
			)
		);
	}

	/** @return void */
	private function authorize(): void {
		check_ajax_referer( 'vmbls_scan', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'viralmaisuriyaa-broken-link-scanner' ) ), 403 );
		}
	}
}
