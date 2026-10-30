<?php
/**
 * Admin menu and screen.
 *
 * @package ViralmaisuriyaaBrokenLinkScanner
 */

namespace ViralmaisuriyaaBrokenLinkScanner\Admin;

use ViralmaisuriyaaBrokenLinkScanner\Database\Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {
	private const PAGE_SLUG = 'viralmaisuriyaa-broken-link-scanner';

	/** @return void */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_vmbls_save_settings', array( $this, 'save_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( VMBLS_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/** @return void */
	public function add_menu(): void {
		add_management_page(
			__( 'Viralmaisuriyaa Broken Link Scanner', 'viralmaisuriyaa-broken-link-scanner' ),
			__( 'Broken Link Checker', 'viralmaisuriyaa-broken-link-scanner' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Add action links on Plugins screen.
	 *
	 * @param array<int|string,string> $links Existing action links.
	 * @return array<int|string,string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings_url = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'view' => 'settings',
			),
			admin_url( 'tools.php' )
		);
		$report_url = add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'tools.php' ) );

		$custom = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'viralmaisuriyaa-broken-link-scanner' ) . '</a>',
			'<a href="' . esc_url( $report_url ) . '">' . esc_html__( 'View Report', 'viralmaisuriyaa-broken-link-scanner' ) . '</a>',
		);

		return array_merge( $custom, $links );
	}

	/**
	 * Enqueue screen assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'vmbls-admin', VMBLS_URL . 'assets/css/admin.css', array(), VMBLS_VERSION );
		wp_enqueue_script( 'vmbls-admin', VMBLS_URL . 'assets/js/admin.js', array( 'jquery' ), VMBLS_VERSION, true );
		wp_localize_script(
			'vmbls-admin',
			'vmblsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'vmbls_scan' ),
				'i18n'    => array(
					'starting'  => __( 'Starting scan…', 'viralmaisuriyaa-broken-link-scanner' ),
					'scanning'  => __( 'Scanning…', 'viralmaisuriyaa-broken-link-scanner' ),
					'completed' => __( 'Scan completed.', 'viralmaisuriyaa-broken-link-scanner' ),
					'failed'    => __( 'The scan could not continue.', 'viralmaisuriyaa-broken-link-scanner' ),
					'noRows'    => __( 'No matching results found.', 'viralmaisuriyaa-broken-link-scanner' ),
					'page'      => __( 'Page', 'viralmaisuriyaa-broken-link-scanner' ),
					'of'        => __( 'of', 'viralmaisuriyaa-broken-link-scanner' ),
				),
			)
		);
	}

	/** @return void */
	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'viralmaisuriyaa-broken-link-scanner' ) );
		}
		check_admin_referer( 'vmbls_save_settings' );

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $public_types['attachment'] );

		$requested_types = isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) )
			: array();
		$post_types = array_values( array_intersect( $requested_types, array_values( $public_types ) ) );

		$batch_size = isset( $_POST['batch_size'] ) ? min( 10, max( 1, absint( $_POST['batch_size'] ) ) ) : 2;
		$timeout    = isset( $_POST['request_timeout'] ) ? min( 30, max( 5, absint( $_POST['request_timeout'] ) ) ) : 15;

		update_option( 'vmbls_post_types', $post_types, false );
		update_option( 'vmbls_batch_size', $batch_size, false );
		update_option( 'vmbls_request_timeout', $timeout, false );

		$redirect = add_query_arg(
			array(
				'page'    => self::PAGE_SLUG,
				'view'    => 'settings',
				'updated' => '1',
			),
			admin_url( 'tools.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/** @return void */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'viralmaisuriyaa-broken-link-scanner' ) );
		}

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'report'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap vmbls-wrap">
			<div class="vmbls-header">
				<div>
					<div class="vmbls-kicker"><?php esc_html_e( 'SITE HEALTH TOOL', 'viralmaisuriyaa-broken-link-scanner' ); ?></div>
					<h1><?php esc_html_e( 'Viralmaisuriyaa Broken Link Scanner', 'viralmaisuriyaa-broken-link-scanner' ); ?></h1>
					<p><?php esc_html_e( 'Find broken links, redirects, and missing images across your published WordPress content.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p>
				</div>
				<span class="vmbls-version"><?php echo esc_html( 'v' . VMBLS_VERSION ); ?></span>
			</div>

			<nav class="nav-tab-wrapper vmbls-tabs" aria-label="<?php esc_attr_e( 'Broken Link Checker navigation', 'viralmaisuriyaa-broken-link-scanner' ); ?>">
				<a class="nav-tab <?php echo 'settings' !== $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'tools.php' ) ) ); ?>"><?php esc_html_e( 'Scan & Report', 'viralmaisuriyaa-broken-link-scanner' ); ?></a>
				<a class="nav-tab <?php echo 'settings' === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'view' => 'settings' ), admin_url( 'tools.php' ) ) ); ?>"><?php esc_html_e( 'Settings', 'viralmaisuriyaa-broken-link-scanner' ); ?></a>
			</nav>

			<?php
			if ( 'settings' === $view ) {
				$this->render_settings();
			} else {
				$this->render_report();
			}
			?>
		</div>
		<?php
	}

	/** @return void */
	private function render_settings(): void {
		$public_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $public_types['attachment'] );
		$selected = get_option( 'vmbls_post_types', array() );
		$selected = is_array( $selected ) ? $selected : array();
		if ( empty( $selected ) ) {
			$selected = array_keys( $public_types );
		}
		?>
		<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p></div>
		<?php endif; ?>

		<div class="vmbls-settings-card">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="vmbls_save_settings">
				<?php wp_nonce_field( 'vmbls_save_settings' ); ?>
				<h2><?php esc_html_e( 'Scanner Settings', 'viralmaisuriyaa-broken-link-scanner' ); ?></h2>
				<p class="description"><?php esc_html_e( 'These settings are stored per site, including on WordPress multisite networks.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p>

				<div class="vmbls-setting-row">
					<div>
						<label class="vmbls-setting-label"><?php esc_html_e( 'Content types', 'viralmaisuriyaa-broken-link-scanner' ); ?></label>
						<p class="description"><?php esc_html_e( 'Choose which published public post types are included in scans.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p>
					</div>
					<div class="vmbls-checkbox-grid">
						<?php foreach ( $public_types as $type => $object ) : ?>
							<label><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $selected, true ) ); ?>> <?php echo esc_html( $object->labels->name ); ?></label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="vmbls-setting-row">
					<div><label class="vmbls-setting-label" for="vmbls-batch-size"><?php esc_html_e( 'Batch size', 'viralmaisuriyaa-broken-link-scanner' ); ?></label><p class="description"><?php esc_html_e( 'Number of source pages processed in each AJAX request. Lower values are safer on shared hosting.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p></div>
					<input id="vmbls-batch-size" name="batch_size" type="number" min="1" max="10" value="<?php echo esc_attr( (string) get_option( 'vmbls_batch_size', 2 ) ); ?>">
				</div>

				<div class="vmbls-setting-row">
					<div><label class="vmbls-setting-label" for="vmbls-request-timeout"><?php esc_html_e( 'Request timeout', 'viralmaisuriyaa-broken-link-scanner' ); ?></label><p class="description"><?php esc_html_e( 'Maximum seconds allowed for fetching a source page before falling back to stored content.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p></div>
					<div><input id="vmbls-request-timeout" name="request_timeout" type="number" min="5" max="30" value="<?php echo esc_attr( (string) get_option( 'vmbls_request_timeout', 15 ) ); ?>"> <span><?php esc_html_e( 'seconds', 'viralmaisuriyaa-broken-link-scanner' ); ?></span></div>
				</div>

				<?php submit_button( __( 'Save Settings', 'viralmaisuriyaa-broken-link-scanner' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** @return void */
	private function render_report(): void {
		$repository = new Repository();
		$latest     = $repository->latest_scan();
		$scan_id    = isset( $_GET['scan_id'] ) ? absint( $_GET['scan_id'] ) : ( $latest ? (int) $latest->id : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scan       = $scan_id ? $repository->get_scan( $scan_id ) : null;
		$results    = $scan_id ? $repository->get_results( $scan_id, 'all', 5000 ) : array();
		$scans      = $repository->get_scans( 20 );
		?>
		<div class="vmbls-toolbar">
			<div class="vmbls-actions">
				<button type="button" class="button button-primary button-hero" id="vmbls-start-scan"><span class="dashicons dashicons-search"></span><?php esc_html_e( 'Start New Scan', 'viralmaisuriyaa-broken-link-scanner' ); ?></button>
				<?php if ( $scan_id ) : ?>
					<a class="button button-hero" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=vmbls_export_csv&scan_id=' . $scan_id ), 'vmbls_export_' . $scan_id ) ); ?>"><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Export CSV', 'viralmaisuriyaa-broken-link-scanner' ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( $scans ) : ?>
				<label class="vmbls-scan-picker"><?php esc_html_e( 'Scan history', 'viralmaisuriyaa-broken-link-scanner' ); ?>
					<select id="vmbls-scan-history">
						<?php foreach ( $scans as $history ) : ?>
							<option value="<?php echo esc_attr( (string) $history->id ); ?>" <?php selected( $scan_id, (int) $history->id ); ?>>#<?php echo esc_html( (string) $history->id ); ?> — <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $history->started_at ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
		</div>

		<div id="vmbls-progress-panel" class="vmbls-progress-panel" <?php echo $scan && 'running' === $scan->status ? '' : 'hidden'; ?>>
			<div class="vmbls-progress-head"><strong><?php esc_html_e( 'Scan progress', 'viralmaisuriyaa-broken-link-scanner' ); ?></strong><span id="vmbls-progress-percent">0%</span></div>
			<div class="vmbls-progress-track"><div id="vmbls-progress-bar" class="vmbls-progress-bar"></div></div>
			<p id="vmbls-progress-text" aria-live="polite"></p>
		</div>

		<?php if ( $scan ) : ?>
			<div class="vmbls-summary">
				<div class="vmbls-stat"><span class="dashicons dashicons-admin-page"></span><div><strong><?php echo esc_html( (string) $scan->total_sources ); ?></strong><span><?php esc_html_e( 'Sources', 'viralmaisuriyaa-broken-link-scanner' ); ?></span></div></div>
				<div class="vmbls-stat"><span class="dashicons dashicons-admin-links"></span><div><strong><?php echo esc_html( (string) $scan->total_checks ); ?></strong><span><?php esc_html_e( 'URLs checked', 'viralmaisuriyaa-broken-link-scanner' ); ?></span></div></div>
				<div class="vmbls-stat vmbls-stat-danger"><span class="dashicons dashicons-warning"></span><div><strong><?php echo esc_html( (string) $scan->broken_count ); ?></strong><span><?php esc_html_e( 'Broken', 'viralmaisuriyaa-broken-link-scanner' ); ?></span></div></div>
				<div class="vmbls-stat vmbls-stat-warning"><span class="dashicons dashicons-randomize"></span><div><strong><?php echo esc_html( (string) $scan->redirect_count ); ?></strong><span><?php esc_html_e( 'Redirects', 'viralmaisuriyaa-broken-link-scanner' ); ?></span></div></div>
			</div>

			<div class="vmbls-datatable-card">
				<div class="vmbls-table-title"><div><h2><?php esc_html_e( 'Link Report', 'viralmaisuriyaa-broken-link-scanner' ); ?></h2><p><?php esc_html_e( 'Search, filter, sort, and paginate the current scan results.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p></div><span class="vmbls-result-count" id="vmbls-result-count"></span></div>
				<div class="vmbls-table-controls">
					<label class="vmbls-search"><span class="dashicons dashicons-search"></span><input id="vmbls-table-search" type="search" placeholder="<?php esc_attr_e( 'Search URL, source or anchor…', 'viralmaisuriyaa-broken-link-scanner' ); ?>"></label>
					<select id="vmbls-filter-status"><option value=""><?php esc_html_e( 'All statuses', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="broken"><?php esc_html_e( 'Broken', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="redirect"><?php esc_html_e( 'Redirect', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="ok"><?php esc_html_e( 'OK', 'viralmaisuriyaa-broken-link-scanner' ); ?></option></select>
					<select id="vmbls-filter-type"><option value=""><?php esc_html_e( 'All resources', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="link"><?php esc_html_e( 'Links', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="image"><?php esc_html_e( 'Images', 'viralmaisuriyaa-broken-link-scanner' ); ?></option></select>
					<select id="vmbls-filter-scope"><option value=""><?php esc_html_e( 'All scopes', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="internal"><?php esc_html_e( 'Internal', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="external"><?php esc_html_e( 'External', 'viralmaisuriyaa-broken-link-scanner' ); ?></option></select>
					<select id="vmbls-filter-http"><option value=""><?php esc_html_e( 'All HTTP codes', 'viralmaisuriyaa-broken-link-scanner' ); ?></option><option value="2xx">2xx</option><option value="3xx">3xx</option><option value="4xx">4xx</option><option value="5xx">5xx</option><option value="error"><?php esc_html_e( 'Request error', 'viralmaisuriyaa-broken-link-scanner' ); ?></option></select>
					<select id="vmbls-page-length" aria-label="<?php esc_attr_e( 'Rows per page', 'viralmaisuriyaa-broken-link-scanner' ); ?>"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select>
				</div>

				<div class="vmbls-table-wrap">
				<table class="widefat vmbls-results-table" id="vmbls-results-table">
					<thead><tr>
						<th data-sort="status" tabindex="0"><?php esc_html_e( 'Status', 'viralmaisuriyaa-broken-link-scanner' ); ?><span class="vmbls-sort"></span></th>
						<th data-sort="type" tabindex="0"><?php esc_html_e( 'Type', 'viralmaisuriyaa-broken-link-scanner' ); ?><span class="vmbls-sort"></span></th>
						<th data-sort="source" tabindex="0"><?php esc_html_e( 'Source Page', 'viralmaisuriyaa-broken-link-scanner' ); ?><span class="vmbls-sort"></span></th>
						<th data-sort="anchor" tabindex="0"><?php esc_html_e( 'Anchor / Alt Text', 'viralmaisuriyaa-broken-link-scanner' ); ?><span class="vmbls-sort"></span></th>
						<th data-sort="target" tabindex="0"><?php esc_html_e( 'Target URL', 'viralmaisuriyaa-broken-link-scanner' ); ?><span class="vmbls-sort"></span></th>
						<th data-sort="final" tabindex="0"><?php esc_html_e( 'Final URL', 'viralmaisuriyaa-broken-link-scanner' ); ?><span class="vmbls-sort"></span></th>
						<th data-sort="http" tabindex="0"><?php esc_html_e( 'HTTP', 'viralmaisuriyaa-broken-link-scanner' ); ?><span class="vmbls-sort"></span></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $results as $result ) : ?>
						<tr data-status="<?php echo esc_attr( $result->result_status ); ?>" data-type="<?php echo esc_attr( $result->resource_type ); ?>" data-scope="<?php echo esc_attr( $result->link_scope ); ?>" data-http="<?php echo esc_attr( (string) $result->status_code ); ?>">
							<td data-value="<?php echo esc_attr( $result->result_status ); ?>"><span class="vmbls-status vmbls-status-<?php echo esc_attr( $result->result_status ); ?>"><?php echo esc_html( ucfirst( $result->result_status ) ); ?></span></td>
							<td data-value="<?php echo esc_attr( $result->resource_type . ' ' . $result->link_scope ); ?>"><span class="vmbls-type-icon dashicons <?php echo 'image' === $result->resource_type ? 'dashicons-format-image' : 'dashicons-admin-links'; ?>"></span><?php echo esc_html( ucfirst( $result->resource_type ) . ' · ' . ucfirst( $result->link_scope ) ); ?></td>
							<td data-value="<?php echo esc_attr( $result->source_title ); ?>"><a href="<?php echo esc_url( $result->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $result->source_title ?: $result->source_url ); ?></a></td>
							<td data-value="<?php echo esc_attr( $result->anchor_text ); ?>"><?php echo esc_html( $result->anchor_text ?: '—' ); ?></td>
							<td class="vmbls-url" data-value="<?php echo esc_attr( $result->target_url ); ?>"><a href="<?php echo esc_url( $result->target_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $result->target_url ); ?></a></td>
							<td class="vmbls-url" data-value="<?php echo esc_attr( $result->final_url ); ?>"><?php echo $result->final_url && $result->final_url !== $result->target_url ? esc_html( $result->final_url ) : '—'; ?></td>
							<td data-value="<?php echo esc_attr( (string) $result->status_code ); ?>"><strong><?php echo esc_html( $result->status_code ? (string) $result->status_code : 'ERR' ); ?></strong><?php echo $result->error_message ? '<span class="vmbls-error-detail">' . esc_html( $result->error_message ) . '</span>' : ''; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<div id="vmbls-empty-state" class="vmbls-empty-state" hidden><span class="dashicons dashicons-filter"></span><strong><?php esc_html_e( 'No matching results', 'viralmaisuriyaa-broken-link-scanner' ); ?></strong><p><?php esc_html_e( 'Try changing or clearing the table filters.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p></div>
				</div>
				<div class="vmbls-pagination"><span id="vmbls-table-info"></span><div><button type="button" class="button" id="vmbls-prev-page"><?php esc_html_e( 'Previous', 'viralmaisuriyaa-broken-link-scanner' ); ?></button><span id="vmbls-page-info"></span><button type="button" class="button" id="vmbls-next-page"><?php esc_html_e( 'Next', 'viralmaisuriyaa-broken-link-scanner' ); ?></button></div></div>
			</div>
		<?php else : ?>
			<div class="vmbls-welcome-card"><span class="dashicons dashicons-admin-links"></span><h2><?php esc_html_e( 'Run your first link scan', 'viralmaisuriyaa-broken-link-scanner' ); ?></h2><p><?php esc_html_e( 'The checker will crawl published content and report broken links, redirects, and missing images.', 'viralmaisuriyaa-broken-link-scanner' ); ?></p><button type="button" class="button button-primary button-hero" id="vmbls-start-scan-secondary"><?php esc_html_e( 'Start First Scan', 'viralmaisuriyaa-broken-link-scanner' ); ?></button></div>
		<?php endif; ?>
		<?php
	}
}
