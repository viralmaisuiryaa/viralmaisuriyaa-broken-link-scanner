<?php
/**
 * HTTP link checker.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Http;

use ViralmaisuriyaaBrokenLinkScanner\Support\Url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Checker {
	private const MAX_REDIRECTS = 5;
	private const TIMEOUT       = 10;

	/**
	 * Check URL status and redirects.
	 *
	 * @param string $url Target URL.
	 * @return array<string,mixed>
	 */
	public function check( string $url ): array {
		$current   = $url;
		$redirects = 0;
		$last_code = 0;

		for ( $i = 0; $i <= self::MAX_REDIRECTS; $i++ ) {
			$response = $this->request( $current, 'HEAD' );

			if ( is_wp_error( $response ) ) {
				$response = $this->request( $current, 'GET' );
			}

			if ( is_wp_error( $response ) ) {
				return array(
					'status_code'    => 0,
					'final_url'      => $current,
					'redirect_count' => $redirects,
					'result_status'  => 'broken',
					'error_message'  => $response->get_error_message(),
				);
			}

			$last_code = (int) wp_remote_retrieve_response_code( $response );

			if ( in_array( $last_code, array( 405, 501 ), true ) ) {
				$response = $this->request( $current, 'GET' );
				if ( is_wp_error( $response ) ) {
					return array(
						'status_code'    => 0,
						'final_url'      => $current,
						'redirect_count' => $redirects,
						'result_status'  => 'broken',
						'error_message'  => $response->get_error_message(),
					);
				}
				$last_code = (int) wp_remote_retrieve_response_code( $response );
			}

			if ( $last_code >= 300 && $last_code < 400 ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( ! $location ) {
					break;
				}
				$next = Url::resolve( (string) $location, $current );
				if ( ! $next || $next === $current ) {
					break;
				}
				$current = $next;
				++$redirects;
				continue;
			}

			break;
		}

		if ( $redirects > self::MAX_REDIRECTS ) {
			return array(
				'status_code'    => $last_code,
				'final_url'      => $current,
				'redirect_count' => $redirects,
				'result_status'  => 'broken',
				'error_message'  => __( 'Too many redirects.', 'viralmaisuriyaa-broken-link-scanner' ),
			);
		}

		$status = 'ok';
		if ( $last_code >= 400 || 0 === $last_code ) {
			$status = 'broken';
		} elseif ( $redirects > 0 ) {
			$status = 'redirect';
		}

		return array(
			'status_code'    => $last_code,
			'final_url'      => $current,
			'redirect_count' => $redirects,
			'result_status'  => $status,
			'error_message'  => '',
		);
	}

	/**
	 * Perform safe HTTP request.
	 *
	 * @param string $url URL.
	 * @param string $method Method.
	 * @return array|\WP_Error
	 */
	private function request( string $url, string $method ) {
		$args = array(
			'method'              => $method,
			'timeout'             => self::TIMEOUT,
			'redirection'         => 0,
			'reject_unsafe_urls'  => ! Url::is_internal( $url ),
			'limit_response_size' => 'GET' === $method ? 65536 : 1,
			'user-agent'          => 'Viralmaisuriyaa Broken Link Scanner/' . VMBLS_VERSION . '; ' . home_url( '/' ),
		);

		return wp_remote_request( $url, $args );
	}
}
