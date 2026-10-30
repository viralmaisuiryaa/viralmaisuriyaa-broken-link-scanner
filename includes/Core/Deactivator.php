<?php
/**
 * Plugin deactivation.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {
	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// No scheduled jobs are registered in version 1.0.0.
	}
}
