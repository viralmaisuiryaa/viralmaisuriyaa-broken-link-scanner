<?php
/**
 * Main plugin bootstrap.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Core;

use ViralmaisuriyaaBrokenLinkScanner\Admin\Ajax_Controller;
use ViralmaisuriyaaBrokenLinkScanner\Admin\Csv_Exporter;
use ViralmaisuriyaaBrokenLinkScanner\Admin\Menu;
use ViralmaisuriyaaBrokenLinkScanner\Database\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run(): void {
		add_action( 'wp_initialize_site', array( $this, 'install_new_site' ), 10, 1 );

		if ( is_admin() ) {
			( new Menu() )->register();
			( new Ajax_Controller() )->register();
			( new Csv_Exporter() )->register();
		}
	}

	/**
	 * Install tables for a newly-created multisite site.
	 *
	 * @param \WP_Site $site New site object.
	 * @return void
	 */
	public function install_new_site( $site ): void {
		if ( ! is_multisite() || ! is_plugin_active_for_network( plugin_basename( VMBLS_FILE ) ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		Schema::install();
		restore_current_blog();
	}
}
