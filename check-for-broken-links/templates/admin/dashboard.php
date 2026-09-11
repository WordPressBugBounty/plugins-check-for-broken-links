<?php
/**
 * Dashboard page: site link health, the SEO tools grid and a "needs
 * attention" excerpt. Split off Dashboard & Scan in 3.0.8 -- this page
 * keeps the top-level slug so bookmarks survive, and owns the "Start scan"
 * button. Progress shows here; results live on Broken link scan.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_conn          = wpcbl_connect();
$wpcbl_is_connected  = $wpcbl_conn && $wpcbl_conn->is_connected();
$wpcbl_scan_results_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-scan' );

// Local scan data: the wpcbl_check_for_broken_links_links option, same
// source the old Dashboard & Scan page read.
$wpcbl_links         = get_option( 'wpcbl_check_for_broken_links_links', array() );
$wpcbl_good_links    = isset( $wpcbl_links['good'] ) && is_array( $wpcbl_links['good'] ) ? $wpcbl_links['good'] : array();
$wpcbl_warning_links = isset( $wpcbl_links['warning'] ) && is_array( $wpcbl_links['warning'] ) ? $wpcbl_links['warning'] : array();
$wpcbl_broken_links  = isset( $wpcbl_links['broken'] ) && is_array( $wpcbl_links['broken'] ) ? $wpcbl_links['broken'] : array();
$wpcbl_broken_count  = count( $wpcbl_broken_links );
$wpcbl_warning_count = count( $wpcbl_warning_links );
$wpcbl_total_links   = isset( $wpcbl_links['total'] ) ? (int) $wpcbl_links['total'] : ( count( $wpcbl_good_links ) + $wpcbl_warning_count + $wpcbl_broken_count );

$wpcbl_last_scan = get_option( 'wpcbl_last_scan_summary' );
$wpcbl_has_scan  = is_array( $wpcbl_last_scan ) && ! empty( $wpcbl_last_scan['time'] );

// Three, and only three, dashboard states -- every branch below tests one
// of these, never a bare $wpcbl_broken_count check on its own, so "no scan
// yet" and "scan found nothing" can never collapse into the same branch:
//   no scan yet    -> ! $wpcbl_has_scan
//   scan, problems -> $wpcbl_has_scan && $wpcbl_broken_count > 0
//   scan, clean    -> $wpcbl_has_scan && 0 === $wpcbl_broken_count
$wpcbl_scan_clean = $wpcbl_has_scan && 0 === $wpcbl_broken_count;

$wpcbl_history = get_option( 'wpcbl_scan_history', array() );
if ( ! is_array( $wpcbl_history ) ) {
	$wpcbl_history = array();
}

// 404 / 5xx breakdown of the broken bucket; redirect warnings are the
// separate warning bucket the checker already keeps (see column_type()).
$wpcbl_404_count = 0;
$wpcbl_5xx_count = 0;
foreach ( $wpcbl_broken_links as $wpcbl_broken_link ) {
	$wpcbl_code = isset( $wpcbl_broken_link['code'] ) ? (int) $wpcbl_broken_link['code'] : 0;
	if ( 404 === $wpcbl_code ) {
		++$wpcbl_404_count;
	} elseif ( $wpcbl_code >= 500 && $wpcbl_code < 600 ) {
		++$wpcbl_5xx_count;
	}
}

// Health score: round(100 * healthy / total) over the last scan's links.
// See wpcbl_dashboard_health_score() for why "healthy" means "not broken".
$wpcbl_health_score = $wpcbl_has_scan ? wpcbl_dashboard_health_score( $wpcbl_total_links, $wpcbl_broken_count ) : null;

$wpcbl_prev_health = null;
if ( $wpcbl_has_scan && count( $wpcbl_history ) > 1 ) {
	$wpcbl_prev_run     = $wpcbl_history[1];
	$wpcbl_prev_health  = wpcbl_dashboard_health_score(
		isset( $wpcbl_prev_run['total'] ) ? $wpcbl_prev_run['total'] : 0,
		isset( $wpcbl_prev_run['broken'] ) ? $wpcbl_prev_run['broken'] : 0
	);
}
$wpcbl_health_delta = ( null !== $wpcbl_health_score && null !== $wpcbl_prev_health ) ? ( $wpcbl_health_score - $wpcbl_prev_health ) : null;

// Post types a scan covers, shared by the "ready to scan" sentence below.
// No "pages crawled" figure is derived from this: the plugin does not
// persist a per-scan item count (only links checked survive in the summary
// and history), and the current publish count is not that number either --
// it excludes comments/menus/widgets a scan walks and ignores the link cap,
// so it can read wrong in both directions. A real fix is persisting an
// actual per-scan item count into the summary/history in a later version.
$wpcbl_scan_post_types = wpcbl_scannable_post_types();
$wpcbl_selected_types  = wpcbl_get_option( 'scan_post_types', array() );
if ( ! empty( $wpcbl_selected_types ) && is_array( $wpcbl_selected_types ) ) {
	$wpcbl_scan_post_types = array_intersect_key( $wpcbl_scan_post_types, array_flip( $wpcbl_selected_types ) );
}

// First-run "ready to scan" sentence, unchanged from the old page.
$wpcbl_ready_count  = 0;
$wpcbl_ready_labels = array();
if ( ! $wpcbl_has_scan ) {
	$wpcbl_ready_type_labels = array();
	foreach ( $wpcbl_scan_post_types as $wpcbl_scan_post_type ) {
		$wpcbl_type_counts = wp_count_posts( $wpcbl_scan_post_type->name );
		$wpcbl_type_count  = isset( $wpcbl_type_counts->publish ) ? (int) $wpcbl_type_counts->publish : 0;
		if ( $wpcbl_type_count > 0 ) {
			$wpcbl_ready_count += $wpcbl_type_count;

			$wpcbl_ready_type_labels[ $wpcbl_scan_post_type->name ] = mb_strtolower( $wpcbl_scan_post_type->labels->name );
		}
	}

	if ( wpcbl_has_pro() && 'on' === wpcbl_get_option( 'scan_comments', '' ) ) {
		$wpcbl_comment_counts = wp_count_comments();
		if ( ! empty( $wpcbl_comment_counts->approved ) ) {
			$wpcbl_ready_count += (int) $wpcbl_comment_counts->approved;

			$wpcbl_ready_type_labels['comment'] = __( 'comments', 'check-for-broken-links' );
		}
	}

	foreach ( array( 'post', 'page', 'product' ) as $wpcbl_priority_type ) {
		if ( isset( $wpcbl_ready_type_labels[ $wpcbl_priority_type ] ) ) {
			$wpcbl_ready_labels[] = $wpcbl_ready_type_labels[ $wpcbl_priority_type ];
			unset( $wpcbl_ready_type_labels[ $wpcbl_priority_type ] );
		}
	}
	$wpcbl_ready_labels = array_slice( array_merge( $wpcbl_ready_labels, array_values( $wpcbl_ready_type_labels ) ), 0, 3 );
}

// Automatic scans: frequency label (Pro), matching the old stat card.
$wpcbl_frequency    = (string) wpcbl_get_option( 'scan_frequency', 'never' );
$wpcbl_settings_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-settings' );
$wpcbl_freq_labels  = array(
	'daily'   => __( 'Daily', 'check-for-broken-links' ),
	'weekly'  => __( 'Weekly', 'check-for-broken-links' ),
	'monthly' => __( 'Monthly', 'check-for-broken-links' ),
);

$wpcbl_next_scan_str = '';
if ( wpcbl_has_pro() && isset( $wpcbl_freq_labels[ $wpcbl_frequency ] ) && class_exists( 'WPCBL_Check_Broken_Links_Schedule' ) ) {
	$wpcbl_next_scan_ts  = WPCBL_Check_Broken_Links_Schedule::get_next_scan_timestamp(
		$wpcbl_frequency,
		(string) wpcbl_get_option( 'scan_time', '00:00' ),
		(string) wpcbl_get_option( 'scan_timezone', wp_timezone_string() )
	);
	$wpcbl_next_scan_str = date_i18n( 'j M, H:i', $wpcbl_next_scan_ts );
}

$wpcbl_reports_kept = count( $wpcbl_history );
if ( 0 === $wpcbl_reports_kept && $wpcbl_has_scan ) {
	$wpcbl_reports_kept = 1;
}

// Topbar.
$wpcbl_page_title = __( 'Dashboard', 'check-for-broken-links' );

// Topbar "View scan" only makes sense once there is something to view.
if ( $wpcbl_has_scan ) {
	// A plain link, not a button+onclick: the topbar_actions string goes
	// through wp_kses() in layout-open.php, which strips every on* handler
	// unconditionally. Its allowlist already permits <a href>.
	$wpcbl_topbar_actions = '<a class="cbl-btn" id="wpcbl-view-scan" href="' . esc_url( $wpcbl_scan_results_url ) . '"><span class="cbl-btn-label">'
		. esc_html__( 'View scan', 'check-for-broken-links' ) . '</span></a> ';
	$wpcbl_topbar_actions .= '<button type="button" class="cbl-btn cbl-btn-primary wpcbl-scan-trigger" id="wpcbl-manual-scan"><span class="cbl-btn-label">'
		. esc_html__( 'Start manual scan', 'check-for-broken-links' ) . '</span></button>';
} else {
	$wpcbl_topbar_actions = '<button type="button" class="cbl-btn cbl-btn-primary wpcbl-scan-trigger" id="wpcbl-first-scan"><span class="cbl-btn-label">'
		. esc_html__( 'Start first scan', 'check-for-broken-links' ) . '</span></button>';
}

$wpcbl_hide_footer = ! $wpcbl_has_scan;

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<div id="wpcbl-check-for-broken-links-scan" class="wpcbl-check-for-broken-links-scan">
	<?php // The loader banner the scan JS toggles (.wpcbl-is-scanning / .wpcbl_none). This is where scan progress shows once "Start scan" runs. ?>
	<?php require_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/loader.php'; ?>
</div>

<?php if ( ! $wpcbl_is_connected ) : ?>
	<div class="cbl-dash-connect" id="wpcbl-dash-connect">
		<div class="cbl-dash-connect-row">
			<span class="cbl-dash-connect-badge">
				<span class="cbl-connect-status-dot"></span>
				<?php esc_html_e( 'Not connected', 'check-for-broken-links' ); ?>
			</span>
			<div class="cbl-dash-connect-info">
				<div class="cbl-dash-connect-title"><?php esc_html_e( 'Connect your site to unlock more', 'check-for-broken-links' ); ?></div>
				<div class="cbl-dash-connect-sub"><?php esc_html_e( 'Free brokenlinkchecker.io account, no license keys. Takes a few seconds.', 'check-for-broken-links' ); ?></div>
			</div>
			<button type="button" class="cbl-dash-connect-toggle" id="wpcbl-dash-connect-toggle" aria-expanded="false">
				<?php esc_html_e( 'What do I get?', 'check-for-broken-links' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-settings' ) . '#cbl-connect' ); ?>" class="cbl-btn cbl-btn-primary cbl-btn-sm"><?php esc_html_e( 'Connect this site', 'check-for-broken-links' ); ?></a>
			<button type="button" class="cbl-dash-connect-dismiss" id="wpcbl-dash-connect-dismiss" title="<?php esc_attr_e( 'Dismiss', 'check-for-broken-links' ); ?>">&times;</button>
		</div>
		<div class="cbl-dash-connect-details" id="wpcbl-dash-connect-details" style="display:none;">
			<div class="cbl-dash-connect-perk">
				<span class="cbl-dash-connect-perk-icon cbl-benefit-icon-green"><span class="dashicons dashicons-superhero"></span></span>
				<div>
					<div class="cbl-dash-connect-perk-title"><?php esc_html_e( 'Instant activation', 'check-for-broken-links' ); ?></div>
					<div class="cbl-dash-connect-perk-body"><?php esc_html_e( 'Connect once and every feature unlocks here automatically.', 'check-for-broken-links' ); ?></div>
				</div>
			</div>
			<div class="cbl-dash-connect-perk">
				<span class="cbl-dash-connect-perk-icon cbl-benefit-icon-purple"><span class="dashicons dashicons-grid-view"></span></span>
				<div>
					<div class="cbl-dash-connect-perk-title"><?php esc_html_e( 'All your sites in one place', 'check-for-broken-links' ); ?></div>
					<div class="cbl-dash-connect-perk-body"><?php esc_html_e( 'Manage every connected site from a single dashboard.', 'check-for-broken-links' ); ?></div>
				</div>
			</div>
			<div class="cbl-dash-connect-perk">
				<span class="cbl-dash-connect-perk-icon cbl-benefit-icon-orange"><span class="dashicons dashicons-star-filled"></span></span>
				<div>
					<div class="cbl-dash-connect-perk-title"><?php esc_html_e( 'Early access & member perks', 'check-for-broken-links' ); ?></div>
					<div class="cbl-dash-connect-perk-body"><?php esc_html_e( 'New features and the occasional bonus.', 'check-for-broken-links' ); ?></div>
				</div>
			</div>
			<div class="cbl-dash-connect-perk">
				<span class="cbl-dash-connect-perk-icon cbl-benefit-icon-blue"><span class="dashicons dashicons-chart-line"></span></span>
				<div>
					<div class="cbl-dash-connect-perk-title"><?php esc_html_e( 'Scan history & health', 'check-for-broken-links' ); ?></div>
					<div class="cbl-dash-connect-perk-body"><?php esc_html_e( 'Your broken-link trends saved to your account over time.', 'check-for-broken-links' ); ?></div>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>

<div class="cbl-dash-top-grid">

	<div class="cbl-dash-health">
		<div class="cbl-dash-health-top">
			<span class="cbl-dash-health-label"><?php esc_html_e( 'SITE LINK HEALTH', 'check-for-broken-links' ); ?></span>
			<?php if ( $wpcbl_has_scan && wpcbl_has_pro() && isset( $wpcbl_freq_labels[ $wpcbl_frequency ] ) ) : ?>
				<span class="cbl-dash-health-chip"><?php echo esc_html( $wpcbl_freq_labels[ $wpcbl_frequency ] ); ?> <?php esc_html_e( 'auto-scan', 'check-for-broken-links' ); ?></span>
			<?php elseif ( ! $wpcbl_has_scan ) : ?>
				<span class="cbl-dash-health-chip"><?php esc_html_e( 'No scan yet', 'check-for-broken-links' ); ?></span>
			<?php endif; ?>
		</div>
		<div class="cbl-dash-health-score-row">
			<div class="cbl-dash-health-score<?php echo null === $wpcbl_health_score ? ' is-empty' : ''; ?>"><?php echo null === $wpcbl_health_score ? '&middot;' : (int) $wpcbl_health_score; ?></div>
			<div class="cbl-dash-health-meta">
				<?php if ( null === $wpcbl_health_score ) : ?>
					<?php esc_html_e( 'Run a scan to see where links are broken', 'check-for-broken-links' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'out of 100', 'check-for-broken-links' ); ?><br />
					<?php if ( null !== $wpcbl_health_delta && 0 !== $wpcbl_health_delta ) : ?>
						<span class="<?php echo $wpcbl_health_delta > 0 ? 'cbl-dash-health-delta-up' : 'cbl-dash-health-delta-down'; ?>">
							<?php echo $wpcbl_health_delta > 0 ? '&#9650;' : '&#9660;'; ?> <?php echo esc_html( abs( $wpcbl_health_delta ) ); ?> <?php esc_html_e( 'pts since last scan', 'check-for-broken-links' ); ?>
						</span>
					<?php elseif ( null !== $wpcbl_health_delta ) : ?>
						<?php esc_html_e( 'Unchanged since last scan', 'check-for-broken-links' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'First scan on record', 'check-for-broken-links' ); ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="cbl-dash-health-bar">
			<div class="cbl-dash-health-bar-fill" style="width:<?php echo (int) ( null === $wpcbl_health_score ? 0 : $wpcbl_health_score ); ?>%;"></div>
		</div>
		<?php if ( $wpcbl_has_scan ) : ?>
			<div class="cbl-dash-health-stats">
				<div><strong><?php echo esc_html( number_format_i18n( $wpcbl_total_links ) ); ?></strong> <?php esc_html_e( 'links checked', 'check-for-broken-links' ); ?></div>
				<div><strong><?php echo esc_html( number_format_i18n( $wpcbl_broken_count + $wpcbl_warning_count ) ); ?></strong> <?php esc_html_e( 'need attention', 'check-for-broken-links' ); ?></div>
			</div>
		<?php else : ?>
			<div class="cbl-dash-health-meta" style="padding-bottom:0;">
				<?php if ( $wpcbl_ready_count > 0 ) : ?>
					<?php
					printf(
						/* translators: 1: number of items, 2: list of content type labels, e.g. "posts, pages and products". */
						esc_html__( 'Ready to scan %1$s items across your %2$s. Nothing changes without your say-so.', 'check-for-broken-links' ),
						esc_html( number_format_i18n( $wpcbl_ready_count ) ),
						esc_html( wp_sprintf_l( '%l', $wpcbl_ready_labels ) )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'Posts, pages, comments, menus and widgets. Nothing changes without your say-so.', 'check-for-broken-links' ); ?>
				<?php endif; ?>
			</div>
			<button type="button" class="cbl-dash-health-cta wpcbl-scan-trigger"><span class="cbl-btn-label"><?php esc_html_e( 'Start first scan', 'check-for-broken-links' ); ?></span></button>
		<?php endif; ?>
	</div>

	<div class="cbl-dash-side-card">
		<div class="cbl-dash-side-head">
			<span class="cbl-stat-icon <?php echo $wpcbl_scan_clean ? 'cbl-stat-icon-green' : 'cbl-stat-icon-red'; ?>" aria-hidden="true">
				<?php if ( $wpcbl_scan_clean ) : ?>
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m5 13 4.5 4.5L19 7"/></svg>
				<?php else : ?>
					<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
				<?php endif; ?>
			</span>
			<span class="cbl-dash-side-label"><?php esc_html_e( 'BROKEN LINKS', 'check-for-broken-links' ); ?></span>
		</div>
		<?php if ( $wpcbl_scan_clean ) : ?>
			<?php // Scan, clean: $wpcbl_has_scan && 0 === $wpcbl_broken_count. Zero broken links is the best result a scan can report, so it gets a sentence, not a bare zero next to two more zeros that carry no information. ?>
			<div class="cbl-dash-broken-sub"><?php esc_html_e( 'Congratulations. We found 0 broken links.', 'check-for-broken-links' ); ?></div>
			<?php if ( $wpcbl_warning_count > 0 ) : ?>
				<div class="cbl-dash-broken-list">
					<div class="cbl-dash-broken-list-row"><span><?php esc_html_e( 'Redirect warnings', 'check-for-broken-links' ); ?></span><span><?php echo esc_html( number_format_i18n( $wpcbl_warning_count ) ); ?></span></div>
				</div>
			<?php endif; ?>
			<button type="button" class="cbl-dash-broken-cta wpcbl-scan-trigger"><span class="cbl-btn-label"><?php esc_html_e( 'Scan again', 'check-for-broken-links' ); ?></span></button>
		<?php elseif ( $wpcbl_has_scan ) : ?>
			<?php // Scan, problems: $wpcbl_has_scan && $wpcbl_broken_count > 0. ?>
			<div class="cbl-dash-broken-value-row">
				<div class="cbl-dash-broken-value"><?php echo esc_html( number_format_i18n( $wpcbl_broken_count ) ); ?></div>
				<div class="cbl-dash-broken-sub"><?php esc_html_e( 'from your last scan', 'check-for-broken-links' ); ?></div>
			</div>
			<div class="cbl-dash-broken-list">
				<div class="cbl-dash-broken-list-row"><span><?php esc_html_e( '404 not found', 'check-for-broken-links' ); ?></span><span><?php echo esc_html( number_format_i18n( $wpcbl_404_count ) ); ?></span></div>
				<div class="cbl-dash-broken-list-row"><span><?php esc_html_e( 'Server error (5xx)', 'check-for-broken-links' ); ?></span><span><?php echo esc_html( number_format_i18n( $wpcbl_5xx_count ) ); ?></span></div>
				<div class="cbl-dash-broken-list-row"><span><?php esc_html_e( 'Redirect warnings', 'check-for-broken-links' ); ?></span><span><?php echo esc_html( number_format_i18n( $wpcbl_warning_count ) ); ?></span></div>
			</div>
			<a href="<?php echo esc_url( $wpcbl_scan_results_url ); ?>" class="cbl-dash-broken-cta"><?php esc_html_e( 'Fix broken links', 'check-for-broken-links' ); ?></a>
		<?php else : ?>
			<?php // No scan yet: ! $wpcbl_has_scan. ?>
			<div class="cbl-dash-broken-value-row">
				<div class="cbl-dash-broken-value is-empty">&middot;</div>
				<div class="cbl-dash-broken-sub"><?php esc_html_e( 'nothing checked yet', 'check-for-broken-links' ); ?></div>
			</div>
			<div class="cbl-dash-broken-list">
				<div class="cbl-dash-broken-list-row"><span><?php esc_html_e( '404 not found', 'check-for-broken-links' ); ?></span><span>&middot;</span></div>
				<div class="cbl-dash-broken-list-row"><span><?php esc_html_e( 'Server error (5xx)', 'check-for-broken-links' ); ?></span><span>&middot;</span></div>
				<div class="cbl-dash-broken-list-row"><span><?php esc_html_e( 'Redirect warnings', 'check-for-broken-links' ); ?></span><span>&middot;</span></div>
			</div>
			<button type="button" class="cbl-dash-broken-cta wpcbl-scan-trigger"><span class="cbl-btn-label"><?php esc_html_e( 'Run a scan to find out', 'check-for-broken-links' ); ?></span></button>
		<?php endif; ?>
	</div>

	<div class="cbl-dash-side-card">
		<span class="cbl-dash-side-label"><?php esc_html_e( 'LAST SCAN', 'check-for-broken-links' ); ?></span>
		<div>
			<div class="cbl-dash-lastscan-value"><?php echo $wpcbl_has_scan ? esc_html( isset( $wpcbl_last_scan['time_str'] ) ? $wpcbl_last_scan['time_str'] : '' ) : esc_html__( 'Never', 'check-for-broken-links' ); ?></div>
			<div class="cbl-dash-lastscan-sub">
				<?php
				if ( $wpcbl_has_scan ) {
					printf(
						/* translators: %s: scan duration in seconds, e.g. 4.2s. */
						esc_html__( 'Completed in %s', 'check-for-broken-links' ),
						esc_html( sprintf( '%.1fs', isset( $wpcbl_last_scan['duration'] ) ? (float) $wpcbl_last_scan['duration'] : 0 ) )
					);
				} else {
					esc_html_e( 'No scan has run on this site yet', 'check-for-broken-links' );
				}
				?>
			</div>
		</div>
		<div class="cbl-dash-divider"></div>
		<div class="cbl-dash-lastscan-rows">
			<div class="cbl-dash-lastscan-row">
				<span><?php esc_html_e( 'Automatic scans', 'check-for-broken-links' ); ?></span>
				<?php if ( wpcbl_has_pro() && isset( $wpcbl_freq_labels[ $wpcbl_frequency ] ) ) : ?>
					<span><?php echo esc_html( '' !== $wpcbl_next_scan_str ? $wpcbl_next_scan_str : $wpcbl_freq_labels[ $wpcbl_frequency ] ); ?></span>
				<?php elseif ( wpcbl_has_pro() ) : ?>
					<span><a href="<?php echo esc_url( $wpcbl_settings_url ); ?>"><?php esc_html_e( 'Turn on', 'check-for-broken-links' ); ?></a></span>
				<?php else : ?>
					<span><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ); ?>"><?php esc_html_e( 'Enable with Pro', 'check-for-broken-links' ); ?></a></span>
				<?php endif; ?>
			</div>
			<div class="cbl-dash-lastscan-row">
				<span><?php esc_html_e( 'Scan reports kept', 'check-for-broken-links' ); ?></span>
				<span><?php echo esc_html( number_format_i18n( $wpcbl_reports_kept ) ); ?></span>
			</div>
		</div>
	</div>

</div>

<div class="cbl-dash-tools-head">
	<h2><?php esc_html_e( 'SEO tools', 'check-for-broken-links' ); ?></h2>
	<span class="cbl-dash-tools-hint"><?php esc_html_e( 'Set each tool up once, numbers appear here', 'check-for-broken-links' ); ?></span>
</div>

<div class="cbl-dash-tools-grid">

	<?php
	// Keyword Rank Tracker: quota.positions_used is the tracked-keyword
	// count; charts.avg_position's last point is the current average.
	$wpcbl_rank_count = null;
	$wpcbl_rank_avg   = null;
	if ( $wpcbl_is_connected && $wpcbl_conn ) {
		$wpcbl_rank_state = $wpcbl_conn->rank_state();
		if ( ! is_wp_error( $wpcbl_rank_state ) && 200 === $wpcbl_rank_state['code'] && ! empty( $wpcbl_rank_state['data']['keywords'] ) ) {
			$wpcbl_rank_count = count( $wpcbl_rank_state['data']['keywords'] );
			if ( ! empty( $wpcbl_rank_state['data']['charts']['avg_position'] ) && is_array( $wpcbl_rank_state['data']['charts']['avg_position'] ) ) {
				$wpcbl_avg_series = array_values( $wpcbl_rank_state['data']['charts']['avg_position'] );
				$wpcbl_last_avg   = end( $wpcbl_avg_series );
				if ( null !== $wpcbl_last_avg && '' !== $wpcbl_last_avg ) {
					$wpcbl_rank_avg = round( (float) $wpcbl_last_avg, 1 );
				}
			}
		}
	}
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-rank-tracker' ) ); ?>" class="cbl-dash-tool-card">
		<div class="cbl-dash-tool-head">
			<span class="cbl-benefit-icon cbl-benefit-icon-purple"><span class="dashicons dashicons-chart-bar"></span></span>
			<span class="cbl-dash-tool-title"><?php esc_html_e( 'Keyword Rank Tracker', 'check-for-broken-links' ); ?></span>
		</div>
		<div class="cbl-dash-tool-value-row">
			<div class="cbl-dash-tool-value<?php echo null === $wpcbl_rank_count ? ' is-empty' : ''; ?>"><?php echo null === $wpcbl_rank_count ? '&middot;' : (int) $wpcbl_rank_count; ?></div>
			<div class="cbl-dash-tool-delta"><?php echo null === $wpcbl_rank_avg ? esc_html__( 'No keywords yet', 'check-for-broken-links' ) : esc_html( sprintf( /* translators: %s: average search position, e.g. 14.2. */ __( 'Avg. position %s', 'check-for-broken-links' ), number_format_i18n( $wpcbl_rank_avg, 1 ) ) ); ?></div>
		</div>
		<div class="cbl-dash-tool-desc"><?php esc_html_e( 'Add the terms you want to rank for, checked weekly.', 'check-for-broken-links' ); ?></div>
		<div class="cbl-dash-tool-cta"><?php echo null === $wpcbl_rank_count ? esc_html__( 'Add keywords', 'check-for-broken-links' ) : esc_html__( 'Open tracker', 'check-for-broken-links' ); ?> <span>&rarr;</span></div>
	</a>

	<?php
	// SEO / AEO Audit: latest.score and pages_audited from seo_audit_state().
	$wpcbl_audit_score  = null;
	$wpcbl_audit_issues = null;
	if ( $wpcbl_is_connected && $wpcbl_conn ) {
		$wpcbl_audit_state = $wpcbl_conn->seo_audit_state();
		if ( ! is_wp_error( $wpcbl_audit_state ) && 200 === $wpcbl_audit_state['code'] && ! empty( $wpcbl_audit_state['data']['latest'] ) ) {
			$wpcbl_latest_audit = $wpcbl_audit_state['data']['latest'];
			if ( isset( $wpcbl_latest_audit['score'] ) && null !== $wpcbl_latest_audit['score'] ) {
				$wpcbl_audit_score = (int) $wpcbl_latest_audit['score'];
			}
			if ( ! empty( $wpcbl_latest_audit['results']['issues'] ) && is_array( $wpcbl_latest_audit['results']['issues'] ) ) {
				$wpcbl_audit_issues = count( $wpcbl_latest_audit['results']['issues'] );
			}
		}
	}
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-seo-audit' ) ); ?>" class="cbl-dash-tool-card">
		<div class="cbl-dash-tool-head">
			<span class="cbl-benefit-icon cbl-benefit-icon-green"><span class="dashicons dashicons-visibility"></span></span>
			<span class="cbl-dash-tool-title"><?php esc_html_e( 'SEO / AEO Audit', 'check-for-broken-links' ); ?></span>
		</div>
		<div class="cbl-dash-tool-value-row">
			<div class="cbl-dash-tool-value<?php echo null === $wpcbl_audit_score ? ' is-empty' : ''; ?>"><?php echo null === $wpcbl_audit_score ? '&middot;' : (int) $wpcbl_audit_score; ?><span class="cbl-dash-tool-value-unit">/100</span></div>
			<div class="cbl-dash-tool-delta<?php echo ( null !== $wpcbl_audit_issues && $wpcbl_audit_issues > 0 ) ? ' is-warn' : ''; ?>">
				<?php
				if ( null === $wpcbl_audit_score ) {
					esc_html_e( 'Not audited yet', 'check-for-broken-links' );
				} elseif ( $wpcbl_audit_issues ) {
					printf(
						/* translators: %d: number of open audit issues. */
						esc_html( _n( '%d issue to fix', '%d issues to fix', $wpcbl_audit_issues, 'check-for-broken-links' ) ),
						(int) $wpcbl_audit_issues
					);
				} else {
					esc_html_e( 'No open issues', 'check-for-broken-links' );
				}
				?>
			</div>
		</div>
		<div class="cbl-dash-tool-desc"><?php esc_html_e( 'Checks titles, schema, headings and AEO readiness.', 'check-for-broken-links' ); ?></div>
		<div class="cbl-dash-tool-cta"><?php echo null === $wpcbl_audit_score ? esc_html__( 'Run first audit', 'check-for-broken-links' ) : esc_html__( 'Run audit', 'check-for-broken-links' ); ?> <span>&rarr;</span></div>
	</a>

	<?php
	// AI Visibility Tracker: overview.mentions is the headline figure,
	// movement only renders when a previous snapshot exists (never a
	// fabricated zero with just one reading). A measured-but-zero reading
	// is 0, not null -- null alone means "never measured", so the check
	// below is `null === overview`, never a falsy/empty check that would
	// also swallow a real zero.
	$wpcbl_aiv_mentions = null;
	$wpcbl_aiv_delta    = null;
	$wpcbl_aiv_prompts  = 0;
	if ( $wpcbl_is_connected && $wpcbl_conn ) {
		$wpcbl_aiv_state = $wpcbl_conn->ai_visibility_state();
		if ( ! is_wp_error( $wpcbl_aiv_state ) && 200 === $wpcbl_aiv_state['code'] && ! empty( $wpcbl_aiv_state['data'] ) ) {
			$wpcbl_aiv_overview = isset( $wpcbl_aiv_state['data']['overview'] ) ? $wpcbl_aiv_state['data']['overview'] : null;
			$wpcbl_aiv_previous = isset( $wpcbl_aiv_state['data']['previous'] ) ? $wpcbl_aiv_state['data']['previous'] : null;
			if ( ! empty( $wpcbl_aiv_state['data']['prompts'] ) && is_array( $wpcbl_aiv_state['data']['prompts'] ) ) {
				$wpcbl_aiv_prompts = count( $wpcbl_aiv_state['data']['prompts'] );
			}
			if ( is_array( $wpcbl_aiv_overview ) && isset( $wpcbl_aiv_overview['mentions'] ) ) {
				$wpcbl_aiv_mentions = (int) $wpcbl_aiv_overview['mentions'];
				if ( is_array( $wpcbl_aiv_previous ) && isset( $wpcbl_aiv_previous['mentions'] ) ) {
					$wpcbl_aiv_delta = $wpcbl_aiv_mentions - (int) $wpcbl_aiv_previous['mentions'];
				}
			}
		}
	}
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-ai-visibility' ) ); ?>" class="cbl-dash-tool-card">
		<div class="cbl-dash-tool-head">
			<span class="cbl-benefit-icon cbl-benefit-icon-purple"><span class="dashicons dashicons-format-chat"></span></span>
			<span class="cbl-dash-tool-title"><?php esc_html_e( 'AI Visibility Tracker', 'check-for-broken-links' ); ?></span>
		</div>
		<div class="cbl-dash-tool-value-row">
			<div class="cbl-dash-tool-value<?php echo null === $wpcbl_aiv_mentions ? ' is-empty' : ''; ?>"><?php echo null === $wpcbl_aiv_mentions ? '&middot;' : (int) $wpcbl_aiv_mentions; ?></div>
			<div class="cbl-dash-tool-delta<?php echo ( null !== $wpcbl_aiv_delta && $wpcbl_aiv_delta < 0 ) ? ' is-down' : ( ( null !== $wpcbl_aiv_delta && $wpcbl_aiv_delta > 0 ) ? ' is-up' : '' ); ?>">
				<?php
				if ( null === $wpcbl_aiv_mentions ) {
					// A prompt list with no reading yet is still tracking --
					// only an empty prompt list plus no reading is genuinely
					// "not tracking". $wpcbl_aiv_prompts comes straight off
					// the payload, never guessed.
					if ( $wpcbl_aiv_prompts > 0 ) {
						esc_html_e( 'First reading pending', 'check-for-broken-links' );
					} else {
						esc_html_e( 'Not tracking', 'check-for-broken-links' );
					}
				} elseif ( null === $wpcbl_aiv_delta ) {
					esc_html_e( 'mentions this reading', 'check-for-broken-links' );
				} elseif ( $wpcbl_aiv_delta > 0 ) {
					printf(
						/* translators: %d: increase in AI mentions since the previous reading. */
						esc_html__( 'Up %d since last reading', 'check-for-broken-links' ),
						(int) $wpcbl_aiv_delta
					);
				} elseif ( $wpcbl_aiv_delta < 0 ) {
					printf(
						/* translators: %d: decrease in AI mentions since the previous reading. */
						esc_html__( 'Down %d since last reading', 'check-for-broken-links' ),
						(int) abs( $wpcbl_aiv_delta )
					);
				} else {
					esc_html_e( 'No change since last reading', 'check-for-broken-links' );
				}
				?>
			</div>
		</div>
		<div class="cbl-dash-tool-desc"><?php esc_html_e( 'See when ChatGPT, Perplexity and Google AI cite you.', 'check-for-broken-links' ); ?></div>
		<div class="cbl-dash-tool-cta"><?php echo ( null === $wpcbl_aiv_mentions && 0 === $wpcbl_aiv_prompts ) ? esc_html__( 'Set up tracking', 'check-for-broken-links' ) : esc_html__( 'View visibility', 'check-for-broken-links' ); ?> <span>&rarr;</span></div>
	</a>

	<?php
	// Internal Link Optimizer: latest.suggestions_count / pages_analyzed.
	$wpcbl_ilo_count = null;
	$wpcbl_ilo_pages = null;
	if ( $wpcbl_is_connected && $wpcbl_conn ) {
		$wpcbl_ilo_state = $wpcbl_conn->internal_links_state();
		if ( ! is_wp_error( $wpcbl_ilo_state ) && 200 === $wpcbl_ilo_state['code'] && ! empty( $wpcbl_ilo_state['data']['latest'] ) ) {
			$wpcbl_latest_ilo = $wpcbl_ilo_state['data']['latest'];
			if ( isset( $wpcbl_latest_ilo['suggestions_count'] ) ) {
				$wpcbl_ilo_count = (int) $wpcbl_latest_ilo['suggestions_count'];
			}
			if ( isset( $wpcbl_latest_ilo['pages_analyzed'] ) ) {
				$wpcbl_ilo_pages = (int) $wpcbl_latest_ilo['pages_analyzed'];
			}
		}
	}
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-internal-links' ) ); ?>" class="cbl-dash-tool-card">
		<div class="cbl-dash-tool-head">
			<span class="cbl-benefit-icon cbl-benefit-icon-blue"><span class="dashicons dashicons-admin-links"></span></span>
			<span class="cbl-dash-tool-title"><?php esc_html_e( 'Internal Link Optimizer', 'check-for-broken-links' ); ?></span>
		</div>
		<div class="cbl-dash-tool-value-row">
			<div class="cbl-dash-tool-value<?php echo null === $wpcbl_ilo_count ? ' is-empty' : ''; ?>"><?php echo null === $wpcbl_ilo_count ? '&middot;' : (int) $wpcbl_ilo_count; ?></div>
			<div class="cbl-dash-tool-delta">
				<?php
				if ( null === $wpcbl_ilo_count ) {
					esc_html_e( 'Needs a scan first', 'check-for-broken-links' );
				} else {
					esc_html_e( 'link opportunities', 'check-for-broken-links' );
				}
				?>
			</div>
		</div>
		<div class="cbl-dash-tool-desc">
			<?php
			if ( null !== $wpcbl_ilo_pages ) {
				printf(
					/* translators: %d: number of pages the last analysis covered. */
					esc_html__( '%d pages analyzed', 'check-for-broken-links' ),
					(int) $wpcbl_ilo_pages
				);
			} else {
				esc_html_e( 'Finds orphan pages and internal link opportunities.', 'check-for-broken-links' );
			}
			?>
		</div>
		<div class="cbl-dash-tool-cta"><?php echo null === $wpcbl_ilo_count ? esc_html__( 'Analyse my site', 'check-for-broken-links' ) : esc_html__( 'Review suggestions', 'check-for-broken-links' ); ?> <span>&rarr;</span></div>
	</a>

	<?php
	// Uptime Monitor: aggregate uptime_month across monitors, incidents in
	// the last 30 days from the incidents list uptime_state() returns.
	$wpcbl_uptime_pct       = null;
	$wpcbl_uptime_down      = 0;
	$wpcbl_uptime_incidents = 0;
	if ( $wpcbl_is_connected && $wpcbl_conn ) {
		$wpcbl_uptime_state = $wpcbl_conn->uptime_state();
		if ( ! is_wp_error( $wpcbl_uptime_state ) && 200 === $wpcbl_uptime_state['code'] && ! empty( $wpcbl_uptime_state['data']['monitors'] ) ) {
			$wpcbl_monitors = $wpcbl_uptime_state['data']['monitors'];
			$wpcbl_sum      = 0.0;
			$wpcbl_n        = 0;
			foreach ( $wpcbl_monitors as $wpcbl_monitor ) {
				if ( isset( $wpcbl_monitor['uptime_month'] ) && is_numeric( $wpcbl_monitor['uptime_month'] ) ) {
					$wpcbl_sum += (float) $wpcbl_monitor['uptime_month'];
					++$wpcbl_n;
				}
				if ( isset( $wpcbl_monitor['status'] ) && 'up' !== $wpcbl_monitor['status'] ) {
					++$wpcbl_uptime_down;
				}
			}
			if ( $wpcbl_n > 0 ) {
				$wpcbl_uptime_pct = round( $wpcbl_sum / $wpcbl_n, 2 );
			}
			if ( ! empty( $wpcbl_uptime_state['data']['incidents'] ) && is_array( $wpcbl_uptime_state['data']['incidents'] ) ) {
				$wpcbl_thirty_days_ago = time() - ( 30 * DAY_IN_SECONDS );
				foreach ( $wpcbl_uptime_state['data']['incidents'] as $wpcbl_incident ) {
					if ( ! empty( $wpcbl_incident['started_at'] ) && strtotime( $wpcbl_incident['started_at'] ) >= $wpcbl_thirty_days_ago ) {
						++$wpcbl_uptime_incidents;
					}
				}
			}
		}
	}
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-uptime' ) ); ?>" class="cbl-dash-tool-card">
		<div class="cbl-dash-tool-head">
			<span class="cbl-benefit-icon cbl-benefit-icon-green"><span class="dashicons dashicons-chart-line"></span></span>
			<span class="cbl-dash-tool-title"><?php esc_html_e( 'Uptime Monitor', 'check-for-broken-links' ); ?></span>
		</div>
		<div class="cbl-dash-tool-value-row">
			<div class="cbl-dash-tool-value<?php echo null === $wpcbl_uptime_pct ? ' is-empty' : ''; ?>"><?php echo null === $wpcbl_uptime_pct ? '&middot;' : esc_html( number_format_i18n( $wpcbl_uptime_pct, 2 ) ); ?><?php echo null === $wpcbl_uptime_pct ? '' : '<span class="cbl-dash-tool-value-unit">%</span>'; ?></div>
			<div class="cbl-dash-tool-delta<?php echo $wpcbl_uptime_down > 0 ? ' is-down' : ( null !== $wpcbl_uptime_pct ? ' is-up' : '' ); ?>">
				<?php
				if ( null === $wpcbl_uptime_pct ) {
					esc_html_e( 'Monitor off', 'check-for-broken-links' );
				} elseif ( $wpcbl_uptime_down > 0 ) {
					printf(
						/* translators: %d: number of monitors currently down. */
						esc_html( _n( '%d monitor down', '%d monitors down', $wpcbl_uptime_down, 'check-for-broken-links' ) ),
						(int) $wpcbl_uptime_down
					);
				} else {
					esc_html_e( 'All systems up', 'check-for-broken-links' );
				}
				?>
			</div>
		</div>
		<div class="cbl-dash-tool-desc">
			<?php
			if ( null !== $wpcbl_uptime_pct ) {
				printf(
					/* translators: %d: number of incidents in the last 30 days. */
					esc_html( _n( '%d incident in 30 days', '%d incidents in 30 days', $wpcbl_uptime_incidents, 'check-for-broken-links' ) ),
					(int) $wpcbl_uptime_incidents
				);
			} else {
				esc_html_e( 'Get an email within 60 seconds of downtime.', 'check-for-broken-links' );
			}
			?>
		</div>
		<div class="cbl-dash-tool-cta"><?php echo null === $wpcbl_uptime_pct ? esc_html__( 'Turn on monitor', 'check-for-broken-links' ) : esc_html__( 'Open monitor', 'check-for-broken-links' ); ?> <span>&rarr;</span></div>
	</a>

	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-seo-tip' ) ); ?>" class="cbl-dash-tip-card">
		<div class="cbl-dash-tool-head">
			<span class="cbl-benefit-icon" style="background:#fff5dc;color:#b8710b;"><span class="dashicons dashicons-lightbulb"></span></span>
			<span class="cbl-dash-tool-title"><?php esc_html_e( 'SEO / AEO tip of the week', 'check-for-broken-links' ); ?></span>
		</div>
		<div class="cbl-dash-tip-body"><?php esc_html_e( 'Your links are healthy. Now let visitors listen. Add audio versions of your posts in two minutes with TTSWP, no account needed.', 'check-for-broken-links' ); ?></div>
		<div class="cbl-dash-tool-cta"><?php esc_html_e( 'See this week\'s tip', 'check-for-broken-links' ); ?> <span>&rarr;</span></div>
	</a>

</div>

<div class="cbl-dash-bottom-grid">

	<div class="cbl-dash-attention-card">
		<div class="cbl-dash-attention-head">
			<span class="cbl-dash-attention-head-title"><?php esc_html_e( 'Needs attention first', 'check-for-broken-links' ); ?></span>
			<?php if ( $wpcbl_broken_count > 0 ) : ?>
				<a href="<?php echo esc_url( $wpcbl_scan_results_url ); ?>" class="cbl-dash-attention-head-link"><?php esc_html_e( 'All results', 'check-for-broken-links' ); ?> &rarr;</a>
			<?php elseif ( $wpcbl_scan_clean ) : ?>
				<span class="cbl-dash-attention-head-link" style="color:var(--cbl-text-hint);"><?php esc_html_e( 'All clear', 'check-for-broken-links' ); ?></span>
			<?php else : ?>
				<span class="cbl-dash-attention-head-link" style="color:var(--cbl-text-hint);"><?php esc_html_e( 'Empty', 'check-for-broken-links' ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $wpcbl_broken_count > 0 ) : ?>
			<?php // Scan, problems: $wpcbl_has_scan && $wpcbl_broken_count > 0. ?>
			<?php foreach ( array_slice( $wpcbl_broken_links, 0, 3 ) as $wpcbl_row ) : ?>
				<?php
				$wpcbl_row_code = isset( $wpcbl_row['code'] ) ? (int) $wpcbl_row['code'] : 0;
				$wpcbl_row_url  = isset( $wpcbl_row['link'] ) ? (string) $wpcbl_row['link'] : '';
				?>
				<div class="cbl-dash-attention-row">
					<span class="cbl-badge cbl-badge-broken"><?php echo esc_html( $wpcbl_row_code > 0 ? (string) $wpcbl_row_code : __( 'Error', 'check-for-broken-links' ) ); ?></span>
					<span class="cbl-dash-attention-url" title="<?php echo esc_attr( $wpcbl_row_url ); ?>"><?php echo esc_html( $wpcbl_row_url ); ?></span>
					<span class="cbl-dash-attention-found"><?php echo esc_html( wpcbl_get_post_or_comment_title( $wpcbl_row ) ); ?></span>
				</div>
			<?php endforeach; ?>
		<?php elseif ( $wpcbl_scan_clean ) : ?>
			<?php // Scan, clean: $wpcbl_has_scan && 0 === $wpcbl_broken_count. A scan ran and found nothing, which reads differently from no scan ever having run. ?>
			<div class="cbl-dash-attention-empty">
				<span class="cbl-dash-attention-empty-icon" style="background:var(--cbl-green-bg);color:var(--cbl-green);">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="m5 13 4.5 4.5L19 7"/></svg>
				</span>
				<span class="cbl-dash-attention-empty-text"><?php esc_html_e( 'Your last scan found no links that need attention.', 'check-for-broken-links' ); ?></span>
			</div>
		<?php else : ?>
			<?php // No scan yet: ! $wpcbl_has_scan. ?>
			<div class="cbl-dash-attention-empty">
				<span class="cbl-dash-attention-empty-icon">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
				</span>
				<span class="cbl-dash-attention-empty-text"><?php esc_html_e( 'Broken links land here after your first scan, worst status codes first.', 'check-for-broken-links' ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<div class="cbl-dash-activity-card">
		<span class="cbl-dash-activity-title"><?php esc_html_e( 'Scan activity', 'check-for-broken-links' ); ?></span>
		<?php
		// Oldest -> newest, last 8 scans on record. Real history, not a
		// weekly rollup the plugin does not keep.
		$wpcbl_activity = array_reverse( array_slice( $wpcbl_history, 0, 8 ) );
		$wpcbl_max_total = 1;
		foreach ( $wpcbl_activity as $wpcbl_run ) {
			$wpcbl_max_total = max( $wpcbl_max_total, isset( $wpcbl_run['total'] ) ? (int) $wpcbl_run['total'] : 0 );
		}
		?>
		<?php if ( count( $wpcbl_activity ) >= 2 ) : ?>
			<div class="cbl-dash-activity-bars">
				<?php foreach ( $wpcbl_activity as $wpcbl_run ) : ?>
					<?php
					$wpcbl_run_total  = isset( $wpcbl_run['total'] ) ? (int) $wpcbl_run['total'] : 0;
					$wpcbl_run_broken = isset( $wpcbl_run['broken'] ) ? (int) $wpcbl_run['broken'] : 0;
					$wpcbl_run_height = max( 4, (int) round( 100 * $wpcbl_run_total / $wpcbl_max_total ) );
					$wpcbl_run_label  = isset( $wpcbl_run['time'] ) ? date_i18n( 'j M', (int) $wpcbl_run['time'] ) : '';
					?>
					<div class="cbl-dash-activity-bar">
						<div class="cbl-dash-activity-fill<?php echo $wpcbl_run_broken > 0 ? ' has-broken' : ''; ?>" style="height:<?php echo (int) $wpcbl_run_height; ?>%;" title="<?php echo esc_attr( sprintf(
							/* translators: 1: links checked, 2: broken links found. */
							__( '%1$s links checked, %2$s broken', 'check-for-broken-links' ),
							number_format_i18n( $wpcbl_run_total ),
							number_format_i18n( $wpcbl_run_broken )
						) ); ?>"></div>
						<span class="cbl-dash-activity-label"><?php echo esc_html( $wpcbl_run_label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="cbl-dash-activity-legend">
				<span class="cbl-dash-activity-legend-item"><span class="cbl-dash-activity-legend-dot"></span><?php esc_html_e( 'Clean scan', 'check-for-broken-links' ); ?></span>
				<span class="cbl-dash-activity-legend-item"><span class="cbl-dash-activity-legend-dot has-broken"></span><?php esc_html_e( 'Broken links found', 'check-for-broken-links' ); ?></span>
			</div>
		<?php else : ?>
			<div class="cbl-dash-activity-bars">
				<?php for ( $wpcbl_i = 0; $wpcbl_i < 8; $wpcbl_i++ ) : ?>
					<div class="cbl-dash-activity-bar"><div class="cbl-dash-activity-fill" style="height:2%;background:var(--cbl-bg-tertiary);"></div></div>
				<?php endfor; ?>
			</div>
			<div class="cbl-dash-activity-empty"><?php esc_html_e( 'Your scan trend builds up here once a few scans have run.', 'check-for-broken-links' ); ?></div>
		<?php endif; ?>
	</div>

</div>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
?>

<?php if ( ! $wpcbl_is_connected ) : ?>
<script>
( function () {
	var toggle = document.getElementById( 'wpcbl-dash-connect-toggle' );
	var details = document.getElementById( 'wpcbl-dash-connect-details' );
	var dismiss = document.getElementById( 'wpcbl-dash-connect-dismiss' );
	var banner = document.getElementById( 'wpcbl-dash-connect' );

	if ( toggle && details ) {
		toggle.addEventListener( 'click', function () {
			var open = details.style.display !== 'none';
			details.style.display = open ? 'none' : 'grid';
			toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
			toggle.textContent = open ? <?php echo wp_json_encode( __( 'What do I get?', 'check-for-broken-links' ) ); ?> : <?php echo wp_json_encode( __( 'Hide benefits', 'check-for-broken-links' ) ); ?>;
		} );
	}

	if ( dismiss && banner ) {
		dismiss.addEventListener( 'click', function () {
			banner.style.display = 'none';
			try {
				window.sessionStorage.setItem( 'wpcblDashConnectDismissed', '1' );
			} catch ( e ) {}
		} );
		try {
			if ( window.sessionStorage.getItem( 'wpcblDashConnectDismissed' ) === '1' ) {
				banner.style.display = 'none';
			}
		} catch ( e ) {}
	}
} )();
</script>
<?php endif; ?>
