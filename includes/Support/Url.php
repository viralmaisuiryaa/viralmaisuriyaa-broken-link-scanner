<?php
/**
 * URL helpers.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Url {
	/**
	 * Resolve a possibly-relative URL against a base URL.
	 *
	 * @param string $url URL.
	 * @param string $base Base URL.
	 * @return string
	 */
	public static function resolve( string $url, string $base ): string {
		$url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( '' === $url || str_starts_with( $url, '#' ) ) {
			return '';
		}

		if ( preg_match( '#^(mailto|tel|javascript|data|sms|whatsapp):#i', $url ) ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			return self::strip_fragment( esc_url_raw( $url ) );
		}

		$parts = wp_parse_url( $base );
		if ( empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = $parts['scheme'] ?? 'https';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$origin = $scheme . '://' . $parts['host'] . $port;

		if ( str_starts_with( $url, '//' ) ) {
			return self::strip_fragment( esc_url_raw( $scheme . ':' . $url ) );
		}

		if ( str_starts_with( $url, '?' ) ) {
			return self::strip_fragment( esc_url_raw( $origin . ( $parts['path'] ?? '/' ) . $url ) );
		}

		if ( str_starts_with( $url, '/' ) ) {
			return self::strip_fragment( esc_url_raw( $origin . $url ) );
		}

		$path = $parts['path'] ?? '/';
		$dir  = preg_replace( '#/[^/]*$#', '/', $path );
		$full = $origin . $dir . $url;

		return self::strip_fragment( esc_url_raw( self::normalize_path( $full ) ) );
	}

	/**
	 * Determine whether URL is internal.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_internal( string $url ): bool {
		$target = wp_parse_url( $url, PHP_URL_HOST );
		$home   = wp_parse_url( home_url(), PHP_URL_HOST );
		return $target && $home && strtolower( (string) $target ) === strtolower( (string) $home );
	}


	/**
	 * Remove URL fragments because they are not sent in HTTP requests.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function strip_fragment( string $url ): string {
		$position = strpos( $url, '#' );
		return false === $position ? $url : substr( $url, 0, $position );
	}

	/**
	 * Normalize path segments.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function normalize_path( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! $parts || empty( $parts['host'] ) ) {
			return $url;
		}

		$segments = explode( '/', $parts['path'] ?? '/' );
		$out      = array();
		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				array_pop( $out );
				continue;
			}
			$out[] = $segment;
		}

		$scheme = $parts['scheme'] ?? 'https';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = '/' . implode( '/', $out );
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		return $scheme . '://' . $parts['host'] . $port . $path . $query;
	}
}
