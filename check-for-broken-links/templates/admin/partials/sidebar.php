<?php
/**
 * Sidebar navigation for the redesigned admin UI.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Current page detection.
$wpcbl_sidebar_page_map = array(
	'wpcbl-check-for-broken-links'              => 'dashboard',
	'wpcbl-check-for-broken-links-scan'         => 'scan-results',
	'wpcbl-check-for-broken-links-reports'      => 'reports',
	'wpcbl-check-for-broken-links-rank-tracker' => 'rank-tracker',
	'wpcbl-check-for-broken-links-uptime'       => 'uptime',
	'wpcbl-check-for-broken-links-seo-audit'   => 'seo-audit',
	'wpcbl-check-for-broken-links-internal-links' => 'internal-links',
	'wpcbl-check-for-broken-links-ai-visibility' => 'ai-visibility',
	'wpcbl-check-for-broken-links-settings'     => 'settings',
	'wpcbl-check-for-broken-links-seo-tip'  => 'seo-tip',
	'wpcbl-check-for-broken-links-help'     => 'help',
	'wpcbl-check-for-broken-links-upgrade'  => 'upgrade',
);

$wpcbl_sidebar_current = '';
if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$wpcbl_sidebar_raw = sanitize_text_field( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $wpcbl_sidebar_page_map[ $wpcbl_sidebar_raw ] ) ) {
		$wpcbl_sidebar_current = $wpcbl_sidebar_page_map[ $wpcbl_sidebar_raw ];
	}
}

$wpcbl_sidebar_active = function ( $slug ) use ( $wpcbl_sidebar_current ) {
	return $wpcbl_sidebar_current === $slug ? ' active' : '';
};
?>

<aside class="cbl-sidebar">

	<div class="cbl-sidebar-logo">
		<div class="cbl-logo-icon">
			<img src="<?php echo esc_url( WPCBL_CHECK_BROKEN_LINKS_ROOT_URL . 'assets/dist/images/cbl-icon.png' ); ?>" width="40" height="40" alt="<?php esc_attr_e( 'Check for Broken Links', 'check-for-broken-links' ); ?>">
		</div>
		<div>
			<div class="cbl-plugin-name"><?php esc_html_e( 'Check for Broken Links', 'check-for-broken-links' ); ?></div>
			<?php
			$wpcbl_conn = function_exists( 'wpcbl_connect' ) ? wpcbl_connect() : null;
			$wpcbl_ver  = 'v' . WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION;
			if ( $wpcbl_conn && $wpcbl_conn->is_connected() ) :
				$wpcbl_conn_meta = $wpcbl_conn->get_connection();
				$wpcbl_conn_plan = ! empty( $wpcbl_conn_meta['plan'] ) ? ucfirst( $wpcbl_conn_meta['plan'] ) : __( 'Free', 'check-for-broken-links' );
				?>
				<span class="cbl-conn-badge cbl-conn-badge-connected">
					<span class="cbl-conn-badge-dot"></span>
					<?php
					/* translators: 1: plugin version (e.g. v3.0.1), 2: plan name. */
					echo esc_html( sprintf( __( '%1$s · %2$s', 'check-for-broken-links' ), $wpcbl_ver, $wpcbl_conn_plan ) );
					?>
				</span>
			<?php elseif ( $wpcbl_conn ) : ?>
				<a class="cbl-conn-badge cbl-conn-badge-disconnected"
					href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-settings' ) . '#cbl-connect' ); ?>">
					<span class="cbl-conn-badge-dot"></span>
					<?php
					/* translators: %s: plugin version (e.g. v3.0.1). */
					echo esc_html( sprintf( __( '%s · Connect site', 'check-for-broken-links' ), $wpcbl_ver ) );
					?> &rarr;
				</a>
			<?php else : ?>
				<span class="cbl-plugin-version"><?php echo esc_html( $wpcbl_ver ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<?php
	// SaaS deep links. wpcbl_go_url() is shared with the admin bar menu so
	// the two cannot drift apart. External items open in a new tab.
	$wpcbl_go = 'wpcbl_go_url';

	// Shown to connected accounts still on Free. A site that has not been
	// connected yet gets the Connect prompt above instead, because connecting
	// is the step that has to happen first either way. A paying customer must
	// never see this.
	$wpcbl_is_free = $wpcbl_conn
		&& $wpcbl_conn->is_connected()
		&& ( empty( $wpcbl_conn_meta['plan'] ) || 'free' === strtolower( (string) $wpcbl_conn_meta['plan'] ) );

	if ( $wpcbl_is_free ) :
		?>
		<a class="cbl-upgrade-pill" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ); ?>">
			<span class="cbl-upgrade-pill-top">
				<span class="dashicons dashicons-star-filled"></span>
				<?php esc_html_e( 'Upgrade to Pro', 'check-for-broken-links' ); ?>
			</span>
			<span class="cbl-upgrade-pill-sub">
				<?php esc_html_e( 'Automatic scans, AI fixes, rank tracking and uptime alerts.', 'check-for-broken-links' ); ?>
			</span>
		</a>
		<?php
	endif;
	?>

	<nav class="cbl-nav">

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'dashboard' ) ); ?>">
			<span class="dashicons dashicons-grid-view"></span>
			<?php esc_html_e( 'Dashboard', 'check-for-broken-links' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-scan' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'scan-results' ) ); ?>">
			<span class="dashicons dashicons-search"></span>
			<?php esc_html_e( 'Broken link scan', 'check-for-broken-links' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-reports' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'reports' ) ); ?>">
			<span class="dashicons dashicons-portfolio"></span>
			<?php esc_html_e( 'Scan reports', 'check-for-broken-links' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-seo-tip' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'seo-tip' ) ); ?>">
			<span class="dashicons dashicons-lightbulb"></span>
			<?php esc_html_e( 'SEO / AEO Tip', 'check-for-broken-links' ); ?>
		</a>

		<span class="cbl-nav-group"><?php esc_html_e( 'SEO Tools', 'check-for-broken-links' ); ?></span>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-rank-tracker' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'rank-tracker' ) ); ?>">
			<span class="dashicons dashicons-chart-bar"></span>
			<?php esc_html_e( 'Keyword Rank Tracker', 'check-for-broken-links' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-seo-audit' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'seo-audit' ) ); ?>">
			<span class="dashicons dashicons-visibility"></span>
			<?php esc_html_e( 'SEO / AEO Audit', 'check-for-broken-links' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-internal-links' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'internal-links' ) ); ?>">
			<span class="dashicons dashicons-admin-links"></span>
			<?php esc_html_e( 'Internal Link Optimizer', 'check-for-broken-links' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-ai-visibility' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'ai-visibility' ) ); ?>">
			<span class="dashicons dashicons-format-chat"></span>
			<?php esc_html_e( 'AI Visibility Tracker', 'check-for-broken-links' ); ?>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-uptime' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'uptime' ) ); ?>">
			<span class="dashicons dashicons-chart-line"></span>
			<?php esc_html_e( 'Uptime Monitor', 'check-for-broken-links' ); ?>
		</a>

		<span class="cbl-nav-group"><?php esc_html_e( 'Account', 'check-for-broken-links' ); ?></span>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-settings' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'settings' ) ); ?>">
			<span class="dashicons dashicons-admin-generic"></span>
			<?php esc_html_e( 'Settings & billing', 'check-for-broken-links' ); ?>
		</a>

		<a href="https://brokenlinkchecker.io/docs" target="_blank" rel="noopener" class="cbl-nav-item cbl-nav-item-top">
			<span class="dashicons dashicons-book"></span>
			<?php esc_html_e( 'Documentation', 'check-for-broken-links' ); ?>
			<span class="cbl-soon-chip"><?php esc_html_e( 'SOON', 'check-for-broken-links' ); ?></span>
			<span class="cbl-nav-ext" aria-hidden="true">&#8599;</span>
		</a>

		<a href="<?php echo esc_url( $wpcbl_go( 'white-label' ) ); ?>" target="_blank" rel="noopener" class="cbl-nav-item cbl-nav-item-top">
			<span class="dashicons dashicons-tag"></span>
			<?php esc_html_e( 'White label', 'check-for-broken-links' ); ?>
			<span class="cbl-nav-ext" aria-hidden="true">&#8599;</span>
		</a>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-help' ) ); ?>"
			class="cbl-nav-item cbl-nav-item-top<?php echo esc_attr( $wpcbl_sidebar_active( 'help' ) ); ?>">
			<span class="dashicons dashicons-editor-help"></span>
			<?php esc_html_e( 'Help', 'check-for-broken-links' ); ?>
		</a>

		<?php // Paid plans never see upgrade prompts (the Roxi rule) — their
		// plan already covers everything, including the upcoming Pro tools. ?>
		<?php if ( ! function_exists( 'wpcbl_has_pro' ) || ! wpcbl_has_pro() ) : ?>
			<div class="cbl-sidebar-cta">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ); ?>"
					class="cbl-btn cbl-btn-primary cbl-sidebar-cta-btn">
					<?php esc_html_e( 'Upgrade to Pro', 'check-for-broken-links' ); ?>
				</a>
			</div>
		<?php endif; ?>

	</nav>

</aside>
