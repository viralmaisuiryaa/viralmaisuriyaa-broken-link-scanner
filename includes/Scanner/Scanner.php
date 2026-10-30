<?php
/**
 * Content scanner.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Scanner;

use ViralmaisuriyaaBrokenLinkScanner\Database\Repository;
use ViralmaisuriyaaBrokenLinkScanner\Http\Checker;
use ViralmaisuriyaaBrokenLinkScanner\Support\Url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Scanner {
	private Repository $repository;
	private Extractor $extractor;
	private Checker $checker;

	public function __construct() {
		$this->repository = new Repository();
		$this->extractor  = new Extractor();
		$this->checker    = new Checker();
	}

	/**
	 * Get supported public post types.
	 *
	 * @return string[]
	 */
	public function post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		$enabled = get_option( 'vmbls_post_types', array() );
		if ( is_array( $enabled ) && ! empty( $enabled ) ) {
			$types = array_intersect_key( $types, array_flip( array_map( 'sanitize_key', $enabled ) ) );
		}
		return array_values( $types );
	}

	/**
	 * Count source posts.
	 *
	 * @return int
	 */
	public function count_sources(): int {
		$query = new \WP_Query(
			array(
				'post_type'              => $this->post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $query->found_posts;
	}


	/**
	 * Fetch rendered front-end HTML, falling back to stored content.
	 *
	 * @param string $source_url Source URL.
	 * @param string $fallback Stored post content.
	 * @return string
	 */
	private function fetch_source_html( string $source_url, string $fallback ): string {
		$response = wp_remote_get(
			$source_url,
			array(
				'timeout'             => min( 30, max( 5, absint( get_option( 'vmbls_request_timeout', 15 ) ) ) ),
				'redirection'         => 3,
				'reject_unsafe_urls'  => false,
				'limit_response_size' => 2097152,
				'user-agent'          => 'Viralmaisuriyaa Broken Link Scanner/' . VMBLS_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $fallback;
		}

		$body = wp_remote_retrieve_body( $response );
		return '' !== $body ? $body : $fallback;
	}

	/**
	 * Scan a page of WordPress content.
	 *
	 * @param int $scan_id Scan ID.
	 * @param int $page Page number.
	 * @param int $per_page Batch size.
	 * @return array<string,int|bool>
	 */
	public function scan_batch( int $scan_id, int $page, int $per_page = 2 ): array {
		$query = new \WP_Query(
			array(
				'post_type'              => $this->post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => max( 1, $page ),
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$checks = 0;
		$broken = 0;
		$redirs = 0;

		foreach ( $query->posts as $post ) {
			$source_url = (string) get_permalink( $post );
			$html       = $this->fetch_source_html( $source_url, $post->post_content );
			$resources  = $this->extractor->extract( $html, $source_url );

			foreach ( $resources as $resource ) {
				$result = $this->checker->check( $resource['url'] );
				++$checks;
				if ( 'broken' === $result['result_status'] ) {
					++$broken;
				} elseif ( 'redirect' === $result['result_status'] ) {
					++$redirs;
				}

				$this->repository->add_result(
					array(
						'scan_id'         => $scan_id,
						'source_post_id'  => (int) $post->ID,
						'source_title'    => get_the_title( $post ),
						'source_url'      => $source_url,
						'target_url'      => $resource['url'],
						'final_url'       => $result['final_url'],
						'anchor_text'     => $resource['anchor'],
						'resource_type'   => $resource['type'],
						'link_scope'      => Url::is_internal( $resource['url'] ) ? 'internal' : 'external',
						'status_code'     => (int) $result['status_code'],
						'result_status'   => $result['result_status'],
						'redirect_count'  => (int) $result['redirect_count'],
						'error_message'   => $result['error_message'],
						'checked_at'      => current_time( 'mysql' ),
					)
				);
			}
		}

		$processed = count( $query->posts );
		$this->repository->increment( $scan_id, $processed, $checks, $broken, $redirs );

		$done = $page >= (int) $query->max_num_pages || 0 === $processed;
		if ( $done ) {
			$this->repository->finish( $scan_id );
		}

		return array(
			'processed' => $processed,
			'checks'    => $checks,
			'broken'    => $broken,
			'redirects' => $redirs,
			'done'      => $done,
		);
	}
}
