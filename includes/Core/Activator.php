<?php
/**
 * Plugin activation.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Core;

use ViralmaisuriyaaBrokenLinkScanner\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {
	/**
	 * Activate plugin.
	 *
	 * @param bool $network_wide Whether network activated.
	 * @return void
	 */
	public static function activate( bool $network_wide ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				Schema::install();
				restore_current_blog();
			}

			return;
		}

		Schema::install();
	}
}
