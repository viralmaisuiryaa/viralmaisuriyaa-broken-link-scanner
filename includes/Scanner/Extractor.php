<?php
/**
 * Extract links and images from HTML.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Scanner;

use ViralmaisuriyaaBrokenLinkScanner\Support\Url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Extractor {
	/**
	 * Extract resources.
	 *
	 * @param string $html HTML content.
	 * @param string $base_url Source URL.
	 * @return array<int,array<string,string>>
	 */
	public function extract( string $html, string $base_url ): array {
		if ( '' === trim( $html ) ) {
			return array();
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();
		$loaded   = $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return array();
		}

		$resources = array();
		$seen      = array();

		foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
			$target = Url::resolve( $node->getAttribute( 'href' ), $base_url );
			if ( ! $target ) {
				continue;
			}
			$key = 'link|' . $target;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$resources[]   = array(
				'url'    => $target,
				'type'   => 'link',
				'anchor' => trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ),
			);
		}

		foreach ( $dom->getElementsByTagName( 'img' ) as $node ) {
			$source = $node->getAttribute( 'src' );
			if ( ! $source && $node->hasAttribute( 'data-src' ) ) {
				$source = $node->getAttribute( 'data-src' );
			}
			$target = Url::resolve( $source, $base_url );
			if ( ! $target ) {
				continue;
			}
			$key = 'image|' . $target;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$resources[]   = array(
				'url'    => $target,
				'type'   => 'image',
				'anchor' => trim( $node->getAttribute( 'alt' ) ),
			);
		}

		return $resources;
	}
}
