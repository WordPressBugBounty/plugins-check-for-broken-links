<?php
/**
 * Settings page (3.1.3 redesign): connection bar, section tabs, and one
 * card per section. Three states share this template: not connected,
 * connected on the free plan, and Pro. Pro never sees an upgrade prompt.
 *
 * The form still posts to options.php under the same option keys, so
 * sanitize_settings() and the autosave below keep working unchanged.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_settings = get_option( 'wpcbl_check_for_broken_links_settings', array() );
$wpcbl_settings = is_array( $wpcbl_settings ) ? $wpcbl_settings : array();
$wpcbl_opt      = static function ( $key, $default = '' ) use ( $wpcbl_settings ) {
	return isset( $wpcbl_settings[ $key ] ) ? $wpcbl_settings[ $key ] : $default;
};
$wpcbl_name     = static function ( $key ) {
	return 'wpcbl_check_for_broken_links_settings[' . $key . ']';
};

$wpcbl_has_pro     = wpcbl_has_pro();
$wpcbl_upgrade_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' );

// Connection.
$wpcbl_connect      = wpcbl_connect();
$wpcbl_is_connected = $wpcbl_connect && $wpcbl_connect->is_connected();
$wpcbl_connection   = $wpcbl_is_connected ? (array) $wpcbl_connect->get_connection() : array();
$wpcbl_entitlements = $wpcbl_is_connected ? (array) $wpcbl_connect->get_entitlements() : array();
$wpcbl_plan         = ! empty( $wpcbl_connection['plan'] ) ? sanitize_key( $wpcbl_connection['plan'] ) : 'free';
$wpcbl_plan_label   = 'free' === $wpcbl_plan
	? __( 'Free plan', 'check-for-broken-links' )
	/* translators: %s: plan name, e.g. Business. */
	: sprintf( __( 'Pro %s plan', 'check-for-broken-links' ), ucfirst( $wpcbl_plan ) );

// The account's site quota only means something on a paid plan: connecting
// sites is free and unlimited.
$wpcbl_quota_used  = isset( $wpcbl_entitlements['quota']['used'] ) ? (int) $wpcbl_entitlements['quota']['used'] : null;
$wpcbl_quota_limit = isset( $wpcbl_entitlements['quota']['limit'] ) ? (int) $wpcbl_entitlements['quota']['limit'] : null;
$wpcbl_show_quota  = 'free' !== $wpcbl_plan && null !== $wpcbl_quota_used && null !== $wpcbl_quota_limit;

// Scan schedule.
$wpcbl_frequency = $wpcbl_opt( 'scan_frequency', 'never' );
$wpcbl_frequency = in_array( $wpcbl_frequency, array( 'daily', 'weekly', 'monthly' ), true ) ? $wpcbl_frequency : 'never';
$wpcbl_scan_time = $wpcbl_opt( 'scan_time', '00:00' );
$wpcbl_scan_tz   = $wpcbl_opt( 'scan_timezone', '' ) ? $wpcbl_opt( 'scan_timezone' ) : wp_timezone_string();

// Manual-offset sites get "+02:00" from wp_timezone_string(), but
// wp_timezone_choice() only matches its own "UTC+2" option format.
if ( preg_match( '/^[+-]/', $wpcbl_scan_tz ) ) {
	$wpcbl_gmt_offset = (float) get_option( 'gmt_offset' );
	$wpcbl_scan_tz    = 'UTC' . ( $wpcbl_gmt_offset >= 0 ? '+' : '' ) . rtrim( rtrim( sprintf( '%.2f', $wpcbl_gmt_offset ), '0' ), '.' );
}

$wpcbl_next_scan = '';
$wpcbl_next_ts   = wp_next_scheduled( WPCBL_Check_Broken_Links_Schedule::EVENT );
if ( $wpcbl_has_pro && 'never' !== $wpcbl_frequency && $wpcbl_next_ts ) {
	try {
		$wpcbl_next_tz = new DateTimeZone( $wpcbl_scan_tz );
	} catch ( Exception $e ) {
		$wpcbl_next_tz = wp_timezone();
	}
	$wpcbl_next = new DateTime( '@' . $wpcbl_next_ts );
	$wpcbl_next->setTimezone( $wpcbl_next_tz );
	$wpcbl_next_scan = $wpcbl_next->format( 'j F Y \a\t H:i' );
}

$wpcbl_frequencies = array(
	'never'   => __( 'Manual', 'check-for-broken-links' ),
	'daily'   => __( 'Daily', 'check-for-broken-links' ),
	'weekly'  => __( 'Weekly', 'check-for-broken-links' ),
	'monthly' => __( 'Monthly', 'check-for-broken-links' ),
);

// What to scan.
$wpcbl_types        = wpcbl_scannable_post_types();
$wpcbl_types_stored = is_array( $wpcbl_opt( 'scan_post_types', array() ) ) ? $wpcbl_opt( 'scan_post_types', array() ) : array();
$wpcbl_types_on     = ! empty( $wpcbl_types_stored ) ? $wpcbl_types_stored : array_keys( $wpcbl_types );
$wpcbl_link_types   = is_array( $wpcbl_opt( 'link_types', array() ) ) && $wpcbl_opt( 'link_types', array() ) ? $wpcbl_opt( 'link_types' ) : array( 'html', 'image' );
$wpcbl_limit_mode   = 'set_number' === $wpcbl_opt( 'number_of_links', 'all' ) ? 'set_number' : 'all';
$wpcbl_limit_value  = (int) $wpcbl_opt( 'set_links_number', 0 );

// A labelled on/off switch posting "on" under the given option key.
$wpcbl_toggle = static function ( $key, $label, $hint = '', $checked = null, $value = 'on', $name = null, $id = null ) use ( $wpcbl_opt, $wpcbl_name ) {
	$checked = null === $checked ? 'on' === $wpcbl_opt( $key ) : $checked;
	$id      = $id ? $id : 'wpcbl-st-' . $key;
	?>
	<label class="cbl-st-toggle" for="<?php echo esc_attr( $id ); ?>">
		<input type="checkbox" class="cbl-st-switch-input" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ? $name : $wpcbl_name( $key ) ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $checked ); ?>>
		<span class="cbl-st-switch" aria-hidden="true"></span>
		<span class="cbl-st-toggle-text">
			<?php echo esc_html( $label ); ?>
			<?php if ( $hint ) : ?>
				<small><?php echo esc_html( $hint ); ?></small>
			<?php endif; ?>
		</span>
	</label>
	<?php
};

$wpcbl_pro_features = array(
	array( __( 'Fix with AI', 'check-for-broken-links' ), __( 'Finds a verified working replacement for each broken link.', 'check-for-broken-links' ) ),
	array( __( 'Auto-fix redirects', 'check-for-broken-links' ), __( 'Replaces redirected URLs with their final destination.', 'check-for-broken-links' ) ),
	array( __( 'Nofollow broken links', 'check-for-broken-links' ), __( 'Adds rel=nofollow until you fix them.', 'check-for-broken-links' ) ),
	array( __( 'Replacement suggestions', 'check-for-broken-links' ), __( 'Archived versions from the Wayback Machine.', 'check-for-broken-links' ) ),
	array( __( 'Notify post authors', 'check-for-broken-links' ), __( 'Emails the author when their post has a broken link.', 'check-for-broken-links' ) ),
	array( __( 'Comments and custom fields', 'check-for-broken-links' ), __( 'Checks comments and post meta, including ACF.', 'check-for-broken-links' ) ),
);

$wpcbl_page_title   = __( 'Settings', 'check-for-broken-links' );
$wpcbl_topbar_meta  = '<span>v' . esc_html( WPCBL_CHECK_BROKEN_LINKS_PLUGIN_VERSION ) . ' · ' . esc_html( $wpcbl_plan_label ) . '</span>';
$wpcbl_topbar_actions = '<button type="button" class="cbl-btn cbl-btn-primary" id="wpcbl-recheck-all">' . esc_html__( 'Re-check all links', 'check-for-broken-links' ) . '</button>';

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<div class="cbl-st">

	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="cbl-banner-success"><?php esc_html_e( 'Settings saved.', 'check-for-broken-links' ); ?></div>
	<?php endif; ?>

	<?php settings_errors( 'wpcbl_connect' ); ?>

	<?php if ( $wpcbl_is_connected ) : ?>
		<div class="cbl-st-conn">
			<span class="cbl-st-dot is-on" aria-hidden="true"></span>
			<div class="cbl-st-conn-text">
				<strong><?php esc_html_e( 'Connected to brokenlinkchecker.io', 'check-for-broken-links' ); ?></strong>
				<span>
					<?php
					echo esc_html( isset( $wpcbl_connection['email'] ) ? $wpcbl_connection['email'] : '' );
					if ( $wpcbl_show_quota ) {
						echo ' · ';
						/* translators: 1: sites used, 2: site limit of the plan. */
						echo esc_html( sprintf( __( '%1$d of %2$d sites used', 'check-for-broken-links' ), $wpcbl_quota_used, $wpcbl_quota_limit ) );
					}
					?>
				</span>
			</div>
			<a class="cbl-st-conn-link" href="https://brokenlinkchecker.io/dashboard" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open dashboard', 'check-for-broken-links' ); ?> <span aria-hidden="true">&#8599;</span></a>
			<details class="cbl-st-menu">
				<summary aria-label="<?php esc_attr_e( 'Connection options', 'check-for-broken-links' ); ?>"><span aria-hidden="true">&#8943;</span></summary>
				<div class="cbl-st-menu-panel">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpcbl_refresh">
						<?php wp_nonce_field( 'wpcbl_refresh' ); ?>
						<button type="submit"><?php esc_html_e( 'Refresh connection', 'check-for-broken-links' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpcbl_disconnect">
						<?php wp_nonce_field( 'wpcbl_disconnect' ); ?>
						<button type="submit" class="is-danger"><?php esc_html_e( 'Disconnect site', 'check-for-broken-links' ); ?></button>
					</form>
				</div>
			</details>
		</div>
	<?php else : ?>
		<div class="cbl-st-conn">
			<span class="cbl-st-dot" aria-hidden="true"></span>
			<div class="cbl-st-conn-text">
				<strong><?php esc_html_e( 'Not connected', 'check-for-broken-links' ); ?></strong>
				<span><?php esc_html_e( 'Connect to a free brokenlinkchecker.io account. No license keys.', 'check-for-broken-links' ); ?></span>
			</div>
			<a class="cbl-btn cbl-btn-primary" href="<?php echo esc_url( wpcbl_connect_url() ); ?>"><?php esc_html_e( 'Connect this site', 'check-for-broken-links' ); ?></a>
		</div>
	<?php endif; ?>

	<nav class="cbl-st-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'check-for-broken-links' ); ?>">
		<a href="#general" class="is-active" aria-current="true"><?php esc_html_e( 'General', 'check-for-broken-links' ); ?></a>
		<a href="#scan"><?php esc_html_e( 'What to scan', 'check-for-broken-links' ); ?></a>
		<a href="#notifications"><?php esc_html_e( 'Notifications', 'check-for-broken-links' ); ?></a>
		<?php if ( $wpcbl_has_pro ) : ?>
			<a href="#seo"><?php esc_html_e( 'SEO', 'check-for-broken-links' ); ?></a>
		<?php endif; ?>
		<span class="cbl-st-autosave" id="cbl-autosave-hint" data-idle="<?php esc_attr_e( 'Changes save automatically', 'check-for-broken-links' ); ?>" data-saving="<?php esc_attr_e( 'Saving…', 'check-for-broken-links' ); ?>" data-saved="<?php esc_attr_e( 'Saved', 'check-for-broken-links' ); ?>"><?php esc_html_e( 'Changes save automatically', 'check-for-broken-links' ); ?></span>
	</nav>

	<form action="options.php" method="post" id="cbl-settings-form" class="cbl-st-form">
		<?php settings_fields( 'wpcbl_check_for_broken_links_settings' ); ?>

		<section id="general" class="cbl-st-card">
			<h2><?php esc_html_e( 'General', 'check-for-broken-links' ); ?></h2>

			<div class="cbl-st-row">
				<div class="cbl-st-label">
					<strong><?php esc_html_e( 'Scan schedule', 'check-for-broken-links' ); ?></strong>
					<span><?php esc_html_e( 'How often links are checked in the background.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-st-control">
					<?php if ( $wpcbl_has_pro ) : ?>
						<div class="cbl-st-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Scan schedule', 'check-for-broken-links' ); ?>">
							<?php foreach ( $wpcbl_frequencies as $wpcbl_value => $wpcbl_label ) : ?>
								<label>
									<input type="radio" name="<?php echo esc_attr( $wpcbl_name( 'scan_frequency' ) ); ?>" value="<?php echo esc_attr( $wpcbl_value ); ?>" <?php checked( $wpcbl_frequency, $wpcbl_value ); ?>>
									<span><?php echo esc_html( $wpcbl_label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="cbl-st-when" id="cbl-st-when" <?php echo 'never' === $wpcbl_frequency ? 'hidden' : ''; ?>>
							<label>
								<span><?php esc_html_e( 'Time', 'check-for-broken-links' ); ?></span>
								<input type="time" class="cbl-st-input" name="<?php echo esc_attr( $wpcbl_name( 'scan_time' ) ); ?>" value="<?php echo esc_attr( $wpcbl_scan_time ); ?>">
							</label>
							<label>
								<span><?php esc_html_e( 'Timezone', 'check-for-broken-links' ); ?></span>
								<select class="cbl-st-input" name="<?php echo esc_attr( $wpcbl_name( 'scan_timezone' ) ); ?>">
									<?php echo wp_timezone_choice( $wpcbl_scan_tz ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</select>
							</label>
						</div>
						<?php if ( $wpcbl_next_scan ) : ?>
							<p class="cbl-st-help">
								<?php
								/* translators: %s: date and time of the next automatic scan. */
								echo esc_html( sprintf( __( 'Next automatic scan: %s.', 'check-for-broken-links' ), $wpcbl_next_scan ) );
								?>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<?php // Free: Manual is the only schedule. The Pro options explain themselves on click. ?>
						<div class="cbl-st-seg">
							<button type="button" class="is-on" aria-pressed="true"><?php esc_html_e( 'Manual', 'check-for-broken-links' ); ?></button>
							<?php foreach ( array( 'daily', 'weekly', 'monthly' ) as $wpcbl_value ) : ?>
								<button type="button" class="cbl-st-pro-option" aria-pressed="false"><?php echo esc_html( $wpcbl_frequencies[ $wpcbl_value ] ); ?> <em><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></em></button>
							<?php endforeach; ?>
						</div>
						<p class="cbl-st-nudge" id="cbl-st-freq-nudge" hidden>
							<?php esc_html_e( 'Scheduled scans are part of Pro.', 'check-for-broken-links' ); ?>
							<a href="#cbl-pro"><?php esc_html_e( 'See Pro features', 'check-for-broken-links' ); ?></a>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<div class="cbl-st-row">
				<div class="cbl-st-label">
					<strong><?php esc_html_e( 'Links per scan', 'check-for-broken-links' ); ?></strong>
					<span><?php esc_html_e( 'Limit this on large sites to keep scans fast.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-st-control cbl-st-inline">
					<div class="cbl-st-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Links per scan', 'check-for-broken-links' ); ?>">
						<label>
							<input type="radio" id="all_links" name="<?php echo esc_attr( $wpcbl_name( 'number_of_links' ) ); ?>" value="all" <?php checked( $wpcbl_limit_mode, 'all' ); ?>>
							<span><?php esc_html_e( 'All links', 'check-for-broken-links' ); ?></span>
						</label>
						<label>
							<input type="radio" id="set_number" name="<?php echo esc_attr( $wpcbl_name( 'number_of_links' ) ); ?>" value="set_number" <?php checked( $wpcbl_limit_mode, 'set_number' ); ?>>
							<span><?php esc_html_e( 'Set a limit', 'check-for-broken-links' ); ?></span>
						</label>
					</div>
					<span class="cbl-st-affix" id="cbl-st-limit" <?php echo 'set_number' === $wpcbl_limit_mode ? '' : 'hidden'; ?>>
						<input type="number" min="1" id="number_of_links" name="<?php echo esc_attr( $wpcbl_name( 'set_links_number' ) ); ?>" value="<?php echo $wpcbl_limit_value > 0 ? esc_attr( $wpcbl_limit_value ) : ''; ?>" placeholder="500" aria-label="<?php esc_attr_e( 'Number of links', 'check-for-broken-links' ); ?>">
						<span><?php esc_html_e( 'links', 'check-for-broken-links' ); ?></span>
					</span>
				</div>
			</div>

			<div class="cbl-st-row">
				<div class="cbl-st-label">
					<strong><label for="wpcbl-timeout"><?php esc_html_e( 'Timeout', 'check-for-broken-links' ); ?></label></strong>
					<span><?php esc_html_e( 'Slower links are marked as a warning, not broken.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-st-control">
					<span class="cbl-st-affix">
						<input type="number" id="wpcbl-timeout" min="5" max="120" step="1" name="<?php echo esc_attr( $wpcbl_name( 'timeout' ) ); ?>" value="<?php echo esc_attr( (int) $wpcbl_opt( 'timeout', 30 ) ); ?>">
						<span><?php esc_html_e( 'seconds', 'check-for-broken-links' ); ?></span>
					</span>
				</div>
			</div>
		</section>

		<section id="scan" class="cbl-st-card">
			<h2><?php esc_html_e( 'What to scan', 'check-for-broken-links' ); ?></h2>

			<div class="cbl-st-row">
				<div class="cbl-st-label">
					<strong><?php esc_html_e( 'Content types', 'check-for-broken-links' ); ?></strong>
					<span><?php esc_html_e( 'Which content is checked for broken links.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-st-control">
					<div class="cbl-st-chips">
						<?php foreach ( $wpcbl_types as $wpcbl_type ) : ?>
							<label class="cbl-st-chip">
								<input type="checkbox" name="<?php echo esc_attr( $wpcbl_name( 'scan_post_types' ) ); ?>[]" value="<?php echo esc_attr( $wpcbl_type->name ); ?>" <?php checked( in_array( $wpcbl_type->name, $wpcbl_types_on, true ) ); ?>>
								<span class="cbl-st-chip-tick" aria-hidden="true"></span>
								<?php echo esc_html( $wpcbl_type->labels->name ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="cbl-st-row">
				<div class="cbl-st-label">
					<strong><?php esc_html_e( 'Link types', 'check-for-broken-links' ); ?></strong>
					<span><?php esc_html_e( 'Broken images show as type Image in results.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-st-control cbl-st-stack">
					<?php
					$wpcbl_toggle( 'link_types', __( 'HTML links', 'check-for-broken-links' ), __( 'Links in <a href>', 'check-for-broken-links' ), in_array( 'html', $wpcbl_link_types, true ), 'html', $wpcbl_name( 'link_types' ) . '[]', 'wpcbl-st-link-html' );
					$wpcbl_toggle( 'link_types', __( 'Images', 'check-for-broken-links' ), __( 'Sources in <img src>', 'check-for-broken-links' ), in_array( 'image', $wpcbl_link_types, true ), 'image', $wpcbl_name( 'link_types' ) . '[]', 'wpcbl-st-link-image' );
					$wpcbl_toggle( 'scan_slider_content', __( 'Slider content', 'check-for-broken-links' ), __( 'Smart Slider and Revolution Slider. Turn off if a slider caches old URLs.', 'check-for-broken-links' ), 'on' === $wpcbl_opt( 'scan_slider_content', 'on' ) );
					if ( $wpcbl_has_pro ) {
						$wpcbl_toggle( 'scan_comments', __( 'Comments', 'check-for-broken-links' ), __( 'Links in comments.', 'check-for-broken-links' ) );
						$wpcbl_toggle( 'scan_custom_fields', __( 'Custom fields', 'check-for-broken-links' ), __( 'Links stored in post meta, including ACF.', 'check-for-broken-links' ) );
					}
					?>
				</div>
			</div>

			<div class="cbl-st-row">
				<div class="cbl-st-label">
					<strong><label for="exclusions"><?php esc_html_e( 'Exclusions', 'check-for-broken-links' ); ?></label></strong>
					<span><?php esc_html_e( 'One rule per line. Links containing the text are skipped. Cart, admin and login URLs are excluded automatically.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-st-control">
					<textarea id="exclusions" class="cbl-st-textarea" rows="4" name="<?php echo esc_attr( $wpcbl_name( 'exclusion_urls' ) ); ?>" placeholder="<?php echo esc_attr( "/tag/\n?ref=" ); ?>"><?php echo esc_textarea( $wpcbl_opt( 'exclusion_urls' ) ); ?></textarea>
				</div>
			</div>

			<div class="cbl-st-row">
				<div class="cbl-st-label">
					<strong><?php esc_html_e( 'URL parameters', 'check-for-broken-links' ); ?></strong>
					<span><?php esc_html_e( 'Used when brokenlinkchecker.io crawls this site for link scans and SEO / AEO audits.', 'check-for-broken-links' ); ?></span>
				</div>
				<div class="cbl-st-control">
					<div class="cbl-st-toggle-row">
						<?php // The hidden "off" posts only when the switch is off, so a missing key keeps the default: on. ?>
						<input type="hidden" name="<?php echo esc_attr( $wpcbl_name( 'remove_url_params' ) ); ?>" value="off">
						<label class="cbl-st-toggle" for="wpcbl-st-remove-url-params">
							<input type="checkbox" class="cbl-st-switch-input" id="wpcbl-st-remove-url-params" name="<?php echo esc_attr( $wpcbl_name( 'remove_url_params' ) ); ?>" value="on" <?php checked( wpcbl_remove_url_params() ); ?>>
							<span class="cbl-st-switch" aria-hidden="true"></span>
							<span class="cbl-st-toggle-text"><?php esc_html_e( 'Remove URL parameters', 'check-for-broken-links' ); ?></span>
						</label>
						<span class="cbl-info cbl-info-end" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'What Remove URL parameters does', 'check-for-broken-links' ); ?>" aria-describedby="wpcbl-url-params-tip">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							<span class="cbl-info-bubble" id="wpcbl-url-params-tip" role="tooltip"><?php echo esc_html( wpcbl_url_params_tip() ); ?></span>
						</span>
					</div>
				</div>
			</div>
		</section>

		<section id="notifications" class="cbl-st-card">
			<h2>
				<?php esc_html_e( 'Notifications', 'check-for-broken-links' ); ?>
				<?php if ( ! $wpcbl_has_pro ) : ?>
					<em class="cbl-st-pro"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></em>
				<?php endif; ?>
			</h2>

			<?php if ( $wpcbl_has_pro ) : ?>
				<div class="cbl-st-row">
					<div class="cbl-st-label">
						<strong><?php esc_html_e( 'Email alerts', 'check-for-broken-links' ); ?></strong>
						<span><?php esc_html_e( 'Send alerts to one or more addresses after each scan.', 'check-for-broken-links' ); ?></span>
					</div>
					<div class="cbl-st-control cbl-st-stack">
						<?php $wpcbl_toggle( 'email_notifications', __( 'Email me when new broken links are found', 'check-for-broken-links' ), '', null, 'on', null, 'wpcbl-st-email-toggle' ); ?>
						<label class="cbl-st-field" id="cbl-st-emails" <?php echo 'on' === $wpcbl_opt( 'email_notifications' ) ? '' : 'hidden'; ?>>
							<span><?php esc_html_e( 'Send to', 'check-for-broken-links' ); ?></span>
							<input type="text" class="cbl-st-input" id="wpcbl-st-email-addresses" name="<?php echo esc_attr( $wpcbl_name( 'email_addresses' ) ); ?>" value="<?php echo esc_attr( $wpcbl_opt( 'email_addresses' ) ); ?>" placeholder="you@example.com">
							<small><?php esc_html_e( 'Separate addresses with commas.', 'check-for-broken-links' ); ?></small>
						</label>
					</div>
				</div>
				<div class="cbl-st-row">
					<div class="cbl-st-label">
						<strong><?php esc_html_e( 'Post authors', 'check-for-broken-links' ); ?></strong>
						<span><?php esc_html_e( 'Authors only get alerts for their own content.', 'check-for-broken-links' ); ?></span>
					</div>
					<div class="cbl-st-control">
						<?php $wpcbl_toggle( 'notify_authors', __( 'Email the post author when their post has a broken link', 'check-for-broken-links' ) ); ?>
					</div>
				</div>
			<?php else : ?>
				<div class="cbl-st-row cbl-st-row-flat">
					<div class="cbl-st-label">
						<strong><?php esc_html_e( 'Email me when new broken links are found', 'check-for-broken-links' ); ?></strong>
						<span><?php esc_html_e( 'Send alerts to one or more addresses after each scan.', 'check-for-broken-links' ); ?></span>
					</div>
					<a class="cbl-st-outline" href="<?php echo esc_url( $wpcbl_upgrade_url ); ?>"><?php esc_html_e( 'Unlock email alerts', 'check-for-broken-links' ); ?></a>
				</div>
			<?php endif; ?>
		</section>

		<?php if ( $wpcbl_has_pro ) : ?>
			<section id="seo" class="cbl-st-card">
				<h2><?php esc_html_e( 'SEO', 'check-for-broken-links' ); ?></h2>
				<div class="cbl-st-row">
					<div class="cbl-st-label">
						<strong><?php esc_html_e( 'Fixes', 'check-for-broken-links' ); ?></strong>
						<span><?php esc_html_e( 'Each fix works on its own. Switch on the ones you want.', 'check-for-broken-links' ); ?></span>
					</div>
					<div class="cbl-st-control cbl-st-stack">
						<?php
						$wpcbl_toggle( 'ai_fix', __( 'Fix with AI', 'check-for-broken-links' ), __( 'Finds a verified working replacement for each broken link.', 'check-for-broken-links' ) );
						$wpcbl_toggle( 'fix_redirects', __( 'Auto-fix redirects', 'check-for-broken-links' ), __( 'Redirected links get a Fix redirect action that uses the final URL.', 'check-for-broken-links' ) );
						$wpcbl_toggle( 'nofollow_broken', __( 'Nofollow broken links', 'check-for-broken-links' ), __( 'Adds rel=nofollow to broken links until you fix them.', 'check-for-broken-links' ) );
						$wpcbl_toggle( 'wayback_suggestions', __( 'Replacement suggestions', 'check-for-broken-links' ), __( 'Broken external links get a View archived version action.', 'check-for-broken-links' ) );
						?>
					</div>
				</div>
			</section>
		<?php endif; ?>
	</form>

	<?php if ( ! $wpcbl_has_pro ) : ?>
		<section class="cbl-st-upsell" id="cbl-pro">
			<div class="cbl-st-upsell-head">
				<div>
					<h2><?php esc_html_e( 'Fix links automatically with Pro', 'check-for-broken-links' ); ?></h2>
					<p><?php esc_html_e( 'Scheduled scans, email alerts and these fixes.', 'check-for-broken-links' ); ?></p>
				</div>
				<a class="cbl-st-upsell-btn" href="<?php echo esc_url( $wpcbl_upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'check-for-broken-links' ); ?></a>
			</div>
			<ul>
				<?php foreach ( $wpcbl_pro_features as $wpcbl_feature ) : ?>
					<li><strong><?php echo esc_html( $wpcbl_feature[0] ); ?></strong><?php echo esc_html( $wpcbl_feature[1] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
</div>

<div class="cbl-save-bar" id="cbl-save-bar">
	<span class="cbl-save-bar-text"><?php esc_html_e( 'Automatic saving failed. Save your changes here.', 'check-for-broken-links' ); ?></span>
	<button type="submit" form="cbl-settings-form" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Save Settings', 'check-for-broken-links' ); ?></button>
</div>

<script>
( function () {
	var form = document.getElementById( 'cbl-settings-form' );
	var bar = document.getElementById( 'cbl-save-bar' );
	var hint = document.getElementById( 'cbl-autosave-hint' );
	if ( ! form ) {
		return;
	}

	function reveal( id, show ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.hidden = ! show;
		}
	}

	// Show the parts of a row that only apply to the current choice. The
	// hidden inputs stay enabled, so autosave never drops their values.
	form.addEventListener( 'change', function ( event ) {
		var t = event.target;
		if ( 'wpcbl_check_for_broken_links_settings[scan_frequency]' === t.name ) {
			reveal( 'cbl-st-when', 'never' !== t.value );
		}
		if ( 'wpcbl_check_for_broken_links_settings[number_of_links]' === t.name ) {
			reveal( 'cbl-st-limit', 'set_number' === t.value );
		}
		if ( 'wpcbl-st-email-toggle' === t.id ) {
			reveal( 'cbl-st-emails', t.checked );
		}
	} );

	// Free: the Pro schedule options explain themselves instead of saving.
	document.querySelectorAll( '.cbl-st-pro-option' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			reveal( 'cbl-st-freq-nudge', true );
		} );
	} );

	// Tabs follow the scroll position. A click marks its tab at once, and
	// the last tab takes over at the bottom of the page, where its short
	// section never reaches the observed band.
	var tabs = document.querySelectorAll( '.cbl-st-tabs a' );
	function activate( id ) {
		tabs.forEach( function ( tab ) {
			var on = tab.getAttribute( 'href' ) === '#' + id;
			tab.classList.toggle( 'is-active', on );
			if ( on ) {
				tab.setAttribute( 'aria-current', 'true' );
			} else {
				tab.removeAttribute( 'aria-current' );
			}
		} );
	}
	function atBottom() {
		return window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
	}
	tabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			activate( tab.getAttribute( 'href' ).slice( 1 ) );
		} );
	} );
	if ( 'IntersectionObserver' in window && tabs.length ) {
		var observer = new IntersectionObserver( function ( entries ) {
			if ( atBottom() ) {
				return;
			}
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					activate( entry.target.id );
				}
			} );
		}, { rootMargin: '-40% 0px -55% 0px' } );
		tabs.forEach( function ( tab ) {
			var section = document.querySelector( tab.getAttribute( 'href' ) );
			if ( section ) {
				observer.observe( section );
			}
		} );
		window.addEventListener( 'scroll', function () {
			if ( atBottom() ) {
				activate( tabs[ tabs.length - 1 ].getAttribute( 'href' ).slice( 1 ) );
			}
		}, { passive: true } );
	}

	// The connection menu closes on Escape and on a click outside it.
	var menu = document.querySelector( '.cbl-st-menu' );
	if ( menu ) {
		document.addEventListener( 'click', function ( event ) {
			if ( menu.open && ! menu.contains( event.target ) ) {
				menu.open = false;
			}
		} );
		menu.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && menu.open ) {
				menu.open = false;
				menu.querySelector( 'summary' ).focus();
			}
		} );
	}

	// Autosave: debounce, POST the form in the background, report next to
	// the tabs. The sticky save bar only appears if a save fails.
	function setHint( state ) {
		if ( hint ) {
			hint.textContent = hint.getAttribute( 'data-' + state );
			hint.classList.toggle( 'is-saved', 'saved' === state );
		}
	}

	var timer = null;
	var revert = null;

	function save() {
		setHint( 'saving' );
		// getAttribute, not form.action: the WP settings form contains a
		// hidden input named "action", which shadows the action property.
		window.fetch( form.getAttribute( 'action' ), {
			method: 'POST',
			body: new FormData( form ),
			credentials: 'same-origin',
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'save-failed' );
			}
			if ( bar ) {
				bar.classList.remove( 'is-visible' );
			}
			setHint( 'saved' );
			window.clearTimeout( revert );
			revert = window.setTimeout( function () { setHint( 'idle' ); }, 2500 );
		} ).catch( function () {
			setHint( 'idle' );
			if ( bar ) {
				bar.classList.add( 'is-visible' );
			}
		} );
	}

	function queueSave() {
		window.clearTimeout( timer );
		timer = window.setTimeout( save, 900 );
	}

	form.addEventListener( 'input', queueSave );
	form.addEventListener( 'change', queueSave );
} )();
</script>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
