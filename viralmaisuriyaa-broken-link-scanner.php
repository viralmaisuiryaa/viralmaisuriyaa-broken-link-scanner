<?php
/**
 * Plugin Name:       Viralmaisuriyaa Broken Link Scanner
 * Plugin URI:        https://www.linkedin.com/in/viralmaisuriyaa/
 * Description:       Crawl WordPress content for broken links, redirects, and broken images with live progress and CSV export.
 * Version:           1.2.1
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Viral Maisuriya
 * Author URI:        https://www.instagram.com/viralmaisuriyaa
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       viralmaisuriyaa-broken-link-scanner
 * Domain Path:       /languages
 * Network:           true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VMBLS_VERSION', '1.2.1' );
define( 'VMBLS_FILE', __FILE__ );
define( 'VMBLS_PATH', plugin_dir_path( __FILE__ ) );
define( 'VMBLS_URL', plugin_dir_url( __FILE__ ) );

require_once VMBLS_PATH . 'includes/Autoloader.php';

\ViralmaisuriyaaBrokenLinkScanner\Autoloader::register();

register_activation_hook( __FILE__, array( \ViralmaisuriyaaBrokenLinkScanner\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \ViralmaisuriyaaBrokenLinkScanner\Core\Deactivator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		$plugin = new \ViralmaisuriyaaBrokenLinkScanner\Core\Plugin();
		$plugin->run();
	}
);
