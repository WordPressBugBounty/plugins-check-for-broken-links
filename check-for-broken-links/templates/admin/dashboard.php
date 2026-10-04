<?php
/**
 * Dashboard page (3.1.4 layout): site link health, the SEO tools grid and a "needs
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

// SEO tools: one state per tool, read through the connect proxies (cached
// there for 5 minutes, refusals for 2). A tool counts as "set up" once it
// has produced a real figure. A site that is not connected gets the first
// step for every tool, and no call is made.
$wpcbl_tools = array(
	'rank'   => array( 'on' => false ),
	'audit'  => array( 'on' => false ),
	'aiv'    => array( 'on' => false ),
	'ilo'    => array( 'on' => false ),
	'uptime' => array( 'on' => false ),
);

if ( $wpcbl_is_connected && $wpcbl_conn ) {
	// Keyword Rank Tracker: keyword count plus the last average position.
	$wpcbl_state = $wpcbl_conn->rank_state();
	if ( ! is_wp_error( $wpcbl_state ) && 200 === $wpcbl_state['code'] && ! empty( $wpcbl_state['data']['keywords'] ) ) {
		$wpcbl_tools['rank'] = array(
			'on'    => true,
			'count' => count( $wpcbl_state['data']['keywords'] ),
			'avg'   => null,
		);
		if ( ! empty( $wpcbl_state['data']['charts']['avg_position'] ) && is_array( $wpcbl_state['data']['charts']['avg_position'] ) ) {
			$wpcbl_avg_series = array_values( $wpcbl_state['data']['charts']['avg_position'] );
			$wpcbl_last_avg   = end( $wpcbl_avg_series );
			if ( null !== $wpcbl_last_avg && '' !== $wpcbl_last_avg ) {
				$wpcbl_tools['rank']['avg'] = round( (float) $wpcbl_last_avg, 1 );
			}
		}
	}

	// SEO / AEO Audit: latest score and its open issues.
	$wpcbl_state = $wpcbl_conn->seo_audit_state();
	if ( ! is_wp_error( $wpcbl_state ) && 200 === $wpcbl_state['code'] && ! empty( $wpcbl_state['data']['latest'] ) ) {
		$wpcbl_latest = $wpcbl_state['data']['latest'];
		if ( isset( $wpcbl_latest['score'] ) && null !== $wpcbl_latest['score'] ) {
			$wpcbl_tools['audit'] = array(
				'on'     => true,
				'score'  => (int) $wpcbl_latest['score'],
				'issues' => ( ! empty( $wpcbl_latest['results']['issues'] ) && is_array( $wpcbl_latest['results']['issues'] ) ) ? count( $wpcbl_latest['results']['issues'] ) : 0,
			);
		}
	}

	// AI Visibility Tracker: mentions in the latest reading. A prompt list
	// with no reading yet is tracking already, so it counts as set up. A
	// measured zero is 0, never null: null alone means "never measured".
	$wpcbl_state = $wpcbl_conn->ai_visibility_state();
	if ( ! is_wp_error( $wpcbl_state ) && 200 === $wpcbl_state['code'] && ! empty( $wpcbl_state['data'] ) ) {
		$wpcbl_overview = isset( $wpcbl_state['data']['overview'] ) ? $wpcbl_state['data']['overview'] : null;
		$wpcbl_previous = isset( $wpcbl_state['data']['previous'] ) ? $wpcbl_state['data']['previous'] : null;
		$wpcbl_prompts  = ( ! empty( $wpcbl_state['data']['prompts'] ) && is_array( $wpcbl_state['data']['prompts'] ) ) ? count( $wpcbl_state['data']['prompts'] ) : 0;
		$wpcbl_mentions = ( is_array( $wpcbl_overview ) && isset( $wpcbl_overview['mentions'] ) ) ? (int) $wpcbl_overview['mentions'] : null;
		if ( null !== $wpcbl_mentions || $wpcbl_prompts > 0 ) {
			$wpcbl_tools['aiv'] = array(
				'on'       => true,
				'mentions' => $wpcbl_mentions,
				'delta'    => ( null !== $wpcbl_mentions && is_array( $wpcbl_previous ) && isset( $wpcbl_previous['mentions'] ) ) ? $wpcbl_mentions - (int) $wpcbl_previous['mentions'] : null,
			);
		}
	}

	// Internal Link Optimizer: suggestions and pages in the latest run.
	$wpcbl_state = $wpcbl_conn->internal_links_state();
	if ( ! is_wp_error( $wpcbl_state ) && 200 === $wpcbl_state['code'] && ! empty( $wpcbl_state['data']['latest'] ) && isset( $wpcbl_state['data']['latest']['suggestions_count'] ) ) {
		$wpcbl_tools['ilo'] = array(
			'on'    => true,
			'count' => (int) $wpcbl_state['data']['latest']['suggestions_count'],
			'pages' => isset( $wpcbl_state['data']['latest']['pages_analyzed'] ) ? (int) $wpcbl_state['data']['latest']['pages_analyzed'] : null,
		);
	}

	// Uptime Monitor: average uptime_month across monitors, monitors down.
	$wpcbl_state = $wpcbl_conn->uptime_state();
	if ( ! is_wp_error( $wpcbl_state ) && 200 === $wpcbl_state['code'] && ! empty( $wpcbl_state['data']['monitors'] ) ) {
		$wpcbl_sum  = 0.0;
		$wpcbl_n    = 0;
		$wpcbl_down = 0;
		foreach ( $wpcbl_state['data']['monitors'] as $wpcbl_monitor ) {
			if ( isset( $wpcbl_monitor['uptime_month'] ) && is_numeric( $wpcbl_monitor['uptime_month'] ) ) {
				$wpcbl_sum += (float) $wpcbl_monitor['uptime_month'];
				++$wpcbl_n;
			}
			if ( isset( $wpcbl_monitor['status'] ) && 'up' !== $wpcbl_monitor['status'] ) {
				++$wpcbl_down;
			}
		}
		$wpcbl_tools['uptime'] = array(
			'on'   => true,
			'pct'  => $wpcbl_n > 0 ? round( $wpcbl_sum / $wpcbl_n, 2 ) : null,
			'down' => $wpcbl_down,
		);
	}
}

$wpcbl_setup_count = count( array_filter( wp_list_pluck( $wpcbl_tools, 'on' ) ) );

// Health card wording. The label bands are fixed so the same score always
// reads the same.
$wpcbl_health_label = '';
$wpcbl_health_tone  = 'muted';
if ( null !== $wpcbl_health_score ) {
	if ( $wpcbl_health_score >= 90 ) {
		$wpcbl_health_label = __( 'Excellent', 'check-for-broken-links' );
		$wpcbl_health_tone  = 'good';
	} elseif ( $wpcbl_health_score >= 70 ) {
		$wpcbl_health_label = __( 'Good', 'check-for-broken-links' );
		$wpcbl_health_tone  = 'good';
	} elseif ( $wpcbl_health_score >= 50 ) {
		$wpcbl_health_label = __( 'Needs work', 'check-for-broken-links' );
		$wpcbl_health_tone  = 'warn';
	} else {
		$wpcbl_health_label = __( 'Poor', 'check-for-broken-links' );
		$wpcbl_health_tone  = 'bad';
	}
} elseif ( ! $wpcbl_has_scan ) {
	$wpcbl_health_label = __( 'No scan yet', 'check-for-broken-links' );
}

if ( null === $wpcbl_health_delta ) {
	$wpcbl_delta_text = __( 'This is your first scan on record.', 'check-for-broken-links' );
} elseif ( $wpcbl_health_delta > 0 ) {
	/* translators: %d: health score points gained since the previous scan. */
	$wpcbl_delta_text = sprintf( _n( 'Up %d point since the last scan.', 'Up %d points since the last scan.', $wpcbl_health_delta, 'check-for-broken-links' ), $wpcbl_health_delta );
} elseif ( $wpcbl_health_delta < 0 ) {
	/* translators: %d: health score points lost since the previous scan. */
	$wpcbl_delta_text = sprintf( _n( 'Down %d point since the last scan.', 'Down %d points since the last scan.', abs( $wpcbl_health_delta ), 'check-for-broken-links' ), abs( $wpcbl_health_delta ) );
} else {
	$wpcbl_delta_text = __( 'Unchanged since the last scan.', 'check-for-broken-links' );
}

// Score ring: r=48, so the circumference is 2 * pi * 48.
$wpcbl_ring_len  = 2 * M_PI * 48;
$wpcbl_ring_fill = null === $wpcbl_health_score ? 0 : $wpcbl_ring_len * $wpcbl_health_score / 100;

$wpcbl_upgrade_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' );
$wpcbl_auto_on     = wpcbl_has_pro() && isset( $wpcbl_freq_labels[ $wpcbl_frequency ] );

// Topbar.
$wpcbl_page_title = __( 'Dashboard', 'check-for-broken-links' );

if ( $wpcbl_has_scan ) {
	$wpcbl_topbar_meta = esc_html(
		sprintf(
			/* translators: 1: date and time of the last scan, 2: scan duration, e.g. 4.2s. */
			__( 'Last scan %1$s · completed in %2$s', 'check-for-broken-links' ),
			isset( $wpcbl_last_scan['time_str'] ) ? $wpcbl_last_scan['time_str'] : '',
			sprintf( '%.1fs', isset( $wpcbl_last_scan['duration'] ) ? (float) $wpcbl_last_scan['duration'] : 0 )
		)
	);

	// A plain link, not a button+onclick: the topbar_actions string goes
	// through wp_kses() in layout-open.php, which strips every on* handler
	// unconditionally. Its allowlist already permits <a href>.
	$wpcbl_topbar_actions = '<a class="cbl-btn" id="wpcbl-view-scan" href="' . esc_url( $wpcbl_scan_results_url ) . '"><span class="cbl-btn-label">'
		. esc_html__( 'View scan', 'check-for-broken-links' ) . '</span></a> ';
	$wpcbl_topbar_actions .= '<button type="button" class="cbl-btn cbl-btn-primary wpcbl-scan-trigger" id="wpcbl-manual-scan"><span class="cbl-btn-label">'
		. esc_html__( 'Start manual scan', 'check-for-broken-links' ) . '</span></button>';
} else {
	$wpcbl_topbar_meta    = esc_html__( 'No scan has run on this site yet', 'check-for-broken-links' );
	$wpcbl_topbar_actions = '<button type="button" class="cbl-btn cbl-btn-primary wpcbl-scan-trigger" id="wpcbl-first-scan"><span class="cbl-btn-label">'
		. esc_html__( 'Start first scan', 'check-for-broken-links' ) . '</span></button>';
}

$wpcbl_hide_footer = ! $wpcbl_has_scan;

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';

/**
 * One SEO tool card. Every card is one link to the tool's page, with the
 * call to action drawn as a button inside it.
 *
 * @param array $card icon, tone, title, page, status, on, desc, value, unit, note, cta, pill.
 */
$wpcbl_tool_card = static function ( $card ) {
	?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $card['page'] ) ); ?>" class="cbl-dv2-tool">
		<span class="cbl-dv2-tool-top">
			<span class="cbl-dv2-tool-icon is-<?php echo esc_attr( $card['tone'] ); ?>" aria-hidden="true"><?php echo $card['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup defined in this template. ?></span>
			<span class="cbl-dv2-pill is-<?php echo esc_attr( $card['pill'] ); ?>"><?php echo esc_html( $card['status'] ); ?></span>
		</span>
		<span class="cbl-dv2-tool-body">
			<span class="cbl-dv2-tool-title"><?php echo esc_html( $card['title'] ); ?></span>
			<?php if ( $card['on'] && '' !== $card['value'] ) : ?>
				<span class="cbl-dv2-tool-figure">
					<span class="cbl-dv2-tool-value"><?php echo esc_html( $card['value'] ); ?></span>
					<span class="cbl-dv2-tool-note"><?php echo esc_html( $card['note'] ); ?></span>
				</span>
			<?php else : ?>
				<span class="cbl-dv2-tool-desc"><?php echo esc_html( $card['on'] ? $card['note'] : $card['desc'] ); ?></span>
			<?php endif; ?>
		</span>
		<span class="cbl-dv2-tool-cta"><?php echo esc_html( $card['cta'] ); ?> <span aria-hidden="true">&rarr;</span></span>
	</a>
	<?php
};
?>

<div id="wpcbl-check-for-broken-links-scan" class="wpcbl-check-for-broken-links-scan">
	<?php // The loader banner the scan JS toggles (.wpcbl-is-scanning / .wpcbl_none). This is where scan progress shows once "Start scan" runs. ?>
	<?php require_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/loader.php'; ?>
</div>

<div class="cbl-dv2">

<?php
// Review request: after three completed scans, until "Maybe later"
// (30 days) or a click through to the review form (for good).
$wpcbl_show_review = $wpcbl_reports_kept >= 3
	&& ! get_option( 'wpcbl_review_dismissed' )
	&& ! get_transient( 'wpcbl_review_later' );
if ( $wpcbl_show_review ) :
	$wpcbl_review_later_url  = wp_nonce_url( admin_url( 'admin-post.php?action=wpcbl_review_dismiss&mode=later' ), 'wpcbl_review_dismiss' );
	$wpcbl_review_review_url = wp_nonce_url( admin_url( 'admin-post.php?action=wpcbl_review_dismiss&mode=review' ), 'wpcbl_review_dismiss' );
	?>
	<div class="cbl-dash-review" id="wpcbl-dash-review">
		<span class="cbl-dash-review-icon" aria-hidden="true"><svg viewBox="0 0 20 20" width="22" height="22" fill="#c47f12"><path d="M10 1.5l2.4 5.2 5.6.7-4.1 3.9 1 5.6-4.9-2.8-4.9 2.8 1-5.6L2 7.4l5.6-.7L10 1.5z"></path></svg></span>
		<div class="cbl-dash-review-text">
			<div class="cbl-dash-review-head">
				<span class="cbl-dash-review-title"><?php esc_html_e( 'Do you love the plugin? Please leave a review', 'check-for-broken-links' ); ?></span>
				<span class="cbl-dash-review-count"><?php
					printf(
						/* translators: %d: number of completed scans. */
						esc_html( _n( '%d scan done', '%d scans done', $wpcbl_reports_kept, 'check-for-broken-links' ) ),
						(int) $wpcbl_reports_kept
					);
				?></span>
			</div>
			<p class="cbl-dash-review-body"><?php esc_html_e( 'Three scans in, and your links are in better shape. A short review on WordPress.org helps us out and keeps us motivated to keep improving the plugin.', 'check-for-broken-links' ); ?></p>
		</div>
		<div class="cbl-dash-review-actions">
			<a href="<?php echo esc_url( $wpcbl_review_later_url ); ?>" class="cbl-dash-review-later"><?php esc_html_e( 'Maybe later', 'check-for-broken-links' ); ?></a>
			<a href="<?php echo esc_url( $wpcbl_review_review_url ); ?>" target="_blank" rel="noopener noreferrer" class="cbl-btn cbl-btn-primary cbl-dash-review-cta"><span aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span> <?php esc_html_e( 'Leave a review', 'check-for-broken-links' ); ?></a>
		</div>
		<a href="<?php echo esc_url( $wpcbl_review_later_url ); ?>" class="cbl-dash-review-close" aria-label="<?php esc_attr_e( 'Dismiss for now', 'check-for-broken-links' ); ?>">&times;</a>
	</div>
<?php endif; ?>

<?php if ( ! $wpcbl_is_connected ) : ?>
	<section class="cbl-dv2-connect" id="wpcbl-dash-connect">
		<div class="cbl-dv2-connect-main">
			<div class="cbl-dv2-connect-head">
				<span class="cbl-dv2-connect-badge"><span aria-hidden="true"></span><?php esc_html_e( 'Site not connected', 'check-for-broken-links' ); ?></span>
				<h2><?php esc_html_e( 'Connect your site to unlock more', 'check-for-broken-links' ); ?></h2>
				<p><?php esc_html_e( 'Free brokenlinkchecker.io account, no license keys. Takes a few seconds.', 'check-for-broken-links' ); ?></p>
			</div>
			<ul class="cbl-dv2-connect-perks">
				<li><span aria-hidden="true">&#10003;</span><?php esc_html_e( 'Keyword rank tracking', 'check-for-broken-links' ); ?></li>
				<li><span aria-hidden="true">&#10003;</span><?php esc_html_e( 'SEO / AEO audit', 'check-for-broken-links' ); ?></li>
				<li><span aria-hidden="true">&#10003;</span><?php esc_html_e( 'AI visibility tracking', 'check-for-broken-links' ); ?></li>
				<li><span aria-hidden="true">&#10003;</span><?php esc_html_e( 'Uptime alerts', 'check-for-broken-links' ); ?></li>
			</ul>
		</div>
		<div class="cbl-dv2-connect-actions">
			<a href="<?php echo esc_url( wpcbl_connect_url() ); ?>" class="cbl-dv2-connect-btn"><?php esc_html_e( 'Connect this site', 'check-for-broken-links' ); ?> <span aria-hidden="true">&rarr;</span></a>
			<a href="<?php echo esc_url( $wpcbl_upgrade_url ); ?>" class="cbl-dv2-connect-more"><?php esc_html_e( 'What do I get?', 'check-for-broken-links' ); ?></a>
		</div>
	</section>
<?php endif; ?>

<section class="cbl-dv2-top">
	<div class="cbl-dv2-card cbl-dv2-health">
		<div class="cbl-dv2-ring">
			<svg width="112" height="112" viewBox="0 0 112 112" aria-hidden="true">
				<circle cx="56" cy="56" r="48" fill="none" stroke="#EFEAFD" stroke-width="10"></circle>
				<?php if ( $wpcbl_ring_fill > 0 ) : ?>
					<circle cx="56" cy="56" r="48" fill="none" stroke="#6B3FE0" stroke-width="10" stroke-linecap="round" stroke-dasharray="<?php echo esc_attr( round( $wpcbl_ring_fill, 1 ) . ' ' . round( $wpcbl_ring_len, 1 ) ); ?>" transform="rotate(-90 56 56)"></circle>
				<?php endif; ?>
			</svg>
			<div class="cbl-dv2-ring-text">
				<div class="cbl-dv2-ring-score<?php echo null === $wpcbl_health_score ? ' is-empty' : ''; ?>"><?php echo null === $wpcbl_health_score ? '&middot;' : (int) $wpcbl_health_score; ?></div>
				<div class="cbl-dv2-ring-sub"><?php esc_html_e( 'out of 100', 'check-for-broken-links' ); ?></div>
			</div>
		</div>
		<div class="cbl-dv2-health-main">
			<div class="cbl-dv2-health-head">
				<div class="cbl-dv2-health-title-row">
					<span class="cbl-dv2-card-title"><?php esc_html_e( 'Site link health', 'check-for-broken-links' ); ?></span>
					<?php if ( '' !== $wpcbl_health_label ) : ?>
						<span class="cbl-dv2-pill is-<?php echo esc_attr( $wpcbl_health_tone ); ?>"><?php echo esc_html( $wpcbl_health_label ); ?></span>
					<?php endif; ?>
				</div>
				<p class="cbl-dv2-muted">
					<?php
					if ( ! $wpcbl_has_scan ) {
						if ( $wpcbl_ready_count > 0 ) {
							printf(
								/* translators: 1: number of items, 2: list of content type labels, e.g. "posts, pages and products". */
								esc_html__( 'Ready to scan %1$s items across your %2$s. Nothing changes without your say-so.', 'check-for-broken-links' ),
								esc_html( number_format_i18n( $wpcbl_ready_count ) ),
								esc_html( wp_sprintf_l( '%l', $wpcbl_ready_labels ) )
							);
						} else {
							esc_html_e( 'Posts, pages, comments, menus and widgets. Nothing changes without your say-so.', 'check-for-broken-links' );
						}
					} elseif ( $wpcbl_scan_clean ) {
						echo esc_html( __( 'Congratulations. We found 0 broken links.', 'check-for-broken-links' ) . ' ' . $wpcbl_delta_text );
					} else {
						echo esc_html(
							sprintf(
								/* translators: %s: number of broken links. */
								_n( 'Your last scan found %s broken link.', 'Your last scan found %s broken links.', $wpcbl_broken_count, 'check-for-broken-links' ),
								number_format_i18n( $wpcbl_broken_count )
							) . ' ' . $wpcbl_delta_text
						);
					}
					?>
				</p>
			</div>
			<?php if ( $wpcbl_has_scan ) : ?>
				<div class="cbl-dv2-stats">
					<div><strong><?php echo esc_html( number_format_i18n( $wpcbl_total_links ) ); ?></strong><span><?php esc_html_e( 'Links checked', 'check-for-broken-links' ); ?></span></div>
					<div<?php echo $wpcbl_broken_count > 0 ? ' class="is-bad"' : ''; ?>><strong><?php echo esc_html( number_format_i18n( $wpcbl_broken_count ) ); ?></strong><span><?php esc_html_e( 'Broken links', 'check-for-broken-links' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $wpcbl_broken_count + $wpcbl_warning_count ) ); ?></strong><span><?php esc_html_e( 'Need attention', 'check-for-broken-links' ); ?></span></div>
				</div>
				<?php if ( $wpcbl_broken_count > 0 ) : ?>
					<div class="cbl-dv2-health-foot">
						<span class="cbl-dv2-muted">
							<?php
							printf(
								/* translators: 1: number of 404 errors, 2: number of 5xx errors, 3: number of redirect warnings. */
								esc_html__( '404 not found: %1$s · Server error (5xx): %2$s · Redirect warnings: %3$s', 'check-for-broken-links' ),
								esc_html( number_format_i18n( $wpcbl_404_count ) ),
								esc_html( number_format_i18n( $wpcbl_5xx_count ) ),
								esc_html( number_format_i18n( $wpcbl_warning_count ) )
							);
							?>
						</span>
						<a href="<?php echo esc_url( $wpcbl_scan_results_url ); ?>" class="cbl-dv2-link"><?php esc_html_e( 'Fix broken links', 'check-for-broken-links' ); ?> <span aria-hidden="true">&rarr;</span></a>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div>
					<button type="button" class="cbl-btn cbl-btn-primary wpcbl-scan-trigger"><span class="cbl-btn-label"><?php esc_html_e( 'Start first scan', 'check-for-broken-links' ); ?></span></button>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="cbl-dv2-card cbl-dv2-scanning">
		<div class="cbl-dv2-card-title"><?php esc_html_e( 'Scanning', 'check-for-broken-links' ); ?></div>
		<div class="cbl-dv2-rows">
			<div><span><?php esc_html_e( 'Last scan', 'check-for-broken-links' ); ?></span><strong><?php echo $wpcbl_has_scan && ! empty( $wpcbl_last_scan['time'] ) ? esc_html( date_i18n( 'j M, H:i', (int) $wpcbl_last_scan['time'] ) ) : esc_html__( 'Never', 'check-for-broken-links' ); ?></strong></div>
			<div><span><?php esc_html_e( 'Scan reports kept', 'check-for-broken-links' ); ?></span><strong><?php echo esc_html( number_format_i18n( $wpcbl_reports_kept ) ); ?></strong></div>
			<div>
				<span><?php esc_html_e( 'Automatic scans', 'check-for-broken-links' ); ?></span>
				<?php if ( $wpcbl_auto_on ) : ?>
					<span class="cbl-dv2-pill is-good"><?php echo esc_html( $wpcbl_freq_labels[ $wpcbl_frequency ] ); ?></span>
				<?php else : ?>
					<span class="cbl-dv2-pill is-muted"><?php esc_html_e( 'Off', 'check-for-broken-links' ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $wpcbl_auto_on && '' !== $wpcbl_next_scan_str ) : ?>
				<div><span><?php esc_html_e( 'Next scan', 'check-for-broken-links' ); ?></span><strong><?php echo esc_html( $wpcbl_next_scan_str ); ?></strong></div>
			<?php endif; ?>
		</div>
		<?php if ( ! wpcbl_has_pro() ) : ?>
			<a href="<?php echo esc_url( $wpcbl_upgrade_url ); ?>" class="cbl-dv2-cta-strip"><span><?php esc_html_e( 'Enable weekly automatic scans with Pro', 'check-for-broken-links' ); ?></span><span aria-hidden="true">&rarr;</span></a>
		<?php elseif ( ! $wpcbl_auto_on ) : ?>
			<a href="<?php echo esc_url( $wpcbl_settings_url ); ?>" class="cbl-dv2-cta-strip"><span><?php esc_html_e( 'Turn on automatic scans', 'check-for-broken-links' ); ?></span><span aria-hidden="true">&rarr;</span></a>
		<?php else : ?>
			<a href="<?php echo esc_url( $wpcbl_settings_url ); ?>" class="cbl-dv2-cta-strip"><span><?php esc_html_e( 'Change the scan schedule', 'check-for-broken-links' ); ?></span><span aria-hidden="true">&rarr;</span></a>
		<?php endif; ?>
	</div>
</section>

<section class="cbl-dv2-tools">
	<div class="cbl-dv2-tools-head">
		<div>
			<h2><?php esc_html_e( 'SEO tools', 'check-for-broken-links' ); ?></h2>
			<p class="cbl-dv2-muted"><?php esc_html_e( 'Set each tool up once. Results appear on its card.', 'check-for-broken-links' ); ?></p>
		</div>
		<div class="cbl-dv2-setup">
			<span>
				<?php
				printf(
					/* translators: 1: number of SEO tools set up, 2: number of SEO tools. */
					esc_html__( '%1$s of %2$s set up', 'check-for-broken-links' ),
					'<strong>' . (int) $wpcbl_setup_count . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Integer in fixed markup.
					5
				);
				?>
			</span>
			<span class="cbl-dv2-setup-track" aria-hidden="true"><span style="width:<?php echo (int) round( 100 * $wpcbl_setup_count / 5 ); ?>%"></span></span>
		</div>
	</div>

	<div class="cbl-dv2-tools-grid">
		<?php
		$wpcbl_t = $wpcbl_tools['rank'];
		$wpcbl_tool_card(
			array(
				'page'   => 'wpcbl-check-for-broken-links-rank-tracker',
				'tone'   => 'purple',
				'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 20v-6M12 20V9M18 20V4"></path></svg>',
				'title'  => __( 'Keyword Rank Tracker', 'check-for-broken-links' ),
				'on'     => $wpcbl_t['on'],
				'pill'   => $wpcbl_t['on'] ? 'good' : 'muted',
				'status' => $wpcbl_t['on'] ? __( 'Tracking', 'check-for-broken-links' ) : __( 'No keywords yet', 'check-for-broken-links' ),
				'desc'   => __( 'Add the terms you want to rank for, checked weekly.', 'check-for-broken-links' ),
				'value'  => ( $wpcbl_t['on'] && null !== $wpcbl_t['avg'] ) ? number_format_i18n( $wpcbl_t['avg'], 1 ) : '',
				'note'   => $wpcbl_t['on']
					? ( null !== $wpcbl_t['avg']
						/* translators: %s: number of tracked keywords. */
						? sprintf( _n( 'avg. position · %s keyword', 'avg. position · %s keywords', $wpcbl_t['count'], 'check-for-broken-links' ), number_format_i18n( $wpcbl_t['count'] ) )
						/* translators: %s: number of tracked keywords. */
						: sprintf( _n( '%s keyword tracked. Positions appear after the first weekly check.', '%s keywords tracked. Positions appear after the first weekly check.', $wpcbl_t['count'], 'check-for-broken-links' ), number_format_i18n( $wpcbl_t['count'] ) ) )
					: '',
				'cta'    => $wpcbl_t['on'] ? __( 'View rankings', 'check-for-broken-links' ) : __( 'Add keywords', 'check-for-broken-links' ),
			)
		);

		$wpcbl_t = $wpcbl_tools['audit'];
		$wpcbl_tool_card(
			array(
				'page'   => 'wpcbl-check-for-broken-links-seo-audit',
				'tone'   => 'green',
				'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l2 2 4-4"></path><path d="M12 3l8 3v6c0 4.5-3.4 8.2-8 9-4.6-.8-8-4.5-8-9V6z"></path></svg>',
				'title'  => __( 'SEO / AEO Audit', 'check-for-broken-links' ),
				'on'     => $wpcbl_t['on'],
				'pill'   => $wpcbl_t['on'] ? 'good' : 'muted',
				'status' => $wpcbl_t['on'] ? __( 'Audited', 'check-for-broken-links' ) : __( 'Not audited yet', 'check-for-broken-links' ),
				'desc'   => __( 'Checks titles, schema, headings and AEO readiness.', 'check-for-broken-links' ),
				'value'  => $wpcbl_t['on'] ? (string) $wpcbl_t['score'] : '',
				'note'   => $wpcbl_t['on']
					? ( $wpcbl_t['issues'] > 0
						/* translators: %s: number of open audit issues. */
						? sprintf( _n( 'audit score · %s issue to fix', 'audit score · %s issues to fix', $wpcbl_t['issues'], 'check-for-broken-links' ), number_format_i18n( $wpcbl_t['issues'] ) )
						: __( 'audit score · no open issues', 'check-for-broken-links' ) )
					: '',
				'cta'    => $wpcbl_t['on'] ? __( 'View report', 'check-for-broken-links' ) : __( 'Run first audit', 'check-for-broken-links' ),
			)
		);

		$wpcbl_t = $wpcbl_tools['aiv'];
		if ( $wpcbl_t['on'] && null !== $wpcbl_t['mentions'] ) {
			if ( null === $wpcbl_t['delta'] || 0 === $wpcbl_t['delta'] ) {
				$wpcbl_aiv_note = __( 'AI mentions in the latest reading', 'check-for-broken-links' );
			} elseif ( $wpcbl_t['delta'] > 0 ) {
				/* translators: %s: increase in AI mentions since the previous reading. */
				$wpcbl_aiv_note = sprintf( __( 'AI mentions · up %s since last reading', 'check-for-broken-links' ), number_format_i18n( $wpcbl_t['delta'] ) );
			} else {
				/* translators: %s: decrease in AI mentions since the previous reading. */
				$wpcbl_aiv_note = sprintf( __( 'AI mentions · down %s since last reading', 'check-for-broken-links' ), number_format_i18n( abs( $wpcbl_t['delta'] ) ) );
			}
		} else {
			$wpcbl_aiv_note = __( 'Prompts saved. The first reading is on its way.', 'check-for-broken-links' );
		}
		$wpcbl_tool_card(
			array(
				'page'   => 'wpcbl-check-for-broken-links-ai-visibility',
				'tone'   => 'purple',
				'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.6L18.5 9l-4.7 1.4L12 15l-1.8-4.6L5.5 9l4.7-1.4z"></path><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"></path></svg>',
				'title'  => __( 'AI Visibility Tracker', 'check-for-broken-links' ),
				'on'     => $wpcbl_t['on'],
				'pill'   => $wpcbl_t['on'] ? 'good' : 'muted',
				'status' => $wpcbl_t['on'] ? __( 'Tracking', 'check-for-broken-links' ) : __( 'Not tracking', 'check-for-broken-links' ),
				'desc'   => __( 'See when ChatGPT, Perplexity and Google AI cite you.', 'check-for-broken-links' ),
				'value'  => ( $wpcbl_t['on'] && null !== $wpcbl_t['mentions'] ) ? number_format_i18n( $wpcbl_t['mentions'] ) : '',
				'note'   => $wpcbl_t['on'] ? $wpcbl_aiv_note : '',
				'cta'    => $wpcbl_t['on'] ? __( 'View citations', 'check-for-broken-links' ) : __( 'Set up tracking', 'check-for-broken-links' ),
			)
		);

		$wpcbl_t = $wpcbl_tools['ilo'];
		$wpcbl_tool_card(
			array(
				'page'   => 'wpcbl-check-for-broken-links-internal-links',
				'tone'   => 'blue',
				'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"></path><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"></path></svg>',
				'title'  => __( 'Internal Link Optimizer', 'check-for-broken-links' ),
				'on'     => $wpcbl_t['on'],
				'pill'   => $wpcbl_t['on'] ? 'good' : 'muted',
				'status' => $wpcbl_t['on'] ? __( 'Analysed', 'check-for-broken-links' ) : __( 'Ready to analyse', 'check-for-broken-links' ),
				'desc'   => __( 'Finds orphan pages and internal link opportunities.', 'check-for-broken-links' ),
				'value'  => $wpcbl_t['on'] ? number_format_i18n( $wpcbl_t['count'] ) : '',
				'note'   => $wpcbl_t['on']
					? ( null !== $wpcbl_t['pages']
						/* translators: %s: number of pages the last analysis covered. */
						? sprintf( _n( 'link opportunities · %s page analysed', 'link opportunities · %s pages analysed', $wpcbl_t['pages'], 'check-for-broken-links' ), number_format_i18n( $wpcbl_t['pages'] ) )
						: __( 'link opportunities', 'check-for-broken-links' ) )
					: '',
				'cta'    => $wpcbl_t['on'] ? __( 'View opportunities', 'check-for-broken-links' ) : __( 'Analyse my site', 'check-for-broken-links' ),
			)
		);

		$wpcbl_t = $wpcbl_tools['uptime'];
		$wpcbl_tool_card(
			array(
				'page'   => 'wpcbl-check-for-broken-links-uptime',
				'tone'   => 'green',
				'icon'   => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3-7 4 14 3-7h4"></path></svg>',
				'title'  => __( 'Uptime Monitor', 'check-for-broken-links' ),
				'on'     => $wpcbl_t['on'],
				'pill'   => $wpcbl_t['on'] ? ( $wpcbl_t['down'] > 0 ? 'bad' : 'good' ) : 'muted',
				'status' => $wpcbl_t['on']
					? ( $wpcbl_t['down'] > 0
						/* translators: %s: number of monitors currently down. */
						? sprintf( _n( '%s down', '%s down', $wpcbl_t['down'], 'check-for-broken-links' ), number_format_i18n( $wpcbl_t['down'] ) )
						: __( 'Online', 'check-for-broken-links' ) )
					: __( 'Monitor off', 'check-for-broken-links' ),
				'desc'   => __( 'Get an email within 60 seconds of downtime.', 'check-for-broken-links' ),
				'value'  => ( $wpcbl_t['on'] && null !== $wpcbl_t['pct'] ) ? number_format_i18n( $wpcbl_t['pct'], 2 ) . '%' : '',
				'note'   => $wpcbl_t['on']
					? ( null !== $wpcbl_t['pct'] ? __( 'uptime, last 30 days', 'check-for-broken-links' ) : __( 'Monitor on. Uptime appears after the first checks.', 'check-for-broken-links' ) )
					: '',
				'cta'    => $wpcbl_t['on'] ? __( 'View uptime', 'check-for-broken-links' ) : __( 'Turn on monitor', 'check-for-broken-links' ),
			)
		);
		?>

		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-seo-tip' ) ); ?>" class="cbl-dv2-tool is-tip">
			<span class="cbl-dv2-tool-top">
				<span class="cbl-dv2-tool-icon is-amber" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 21h4"></path><path d="M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"></path></svg></span>
				<span class="cbl-dv2-tip-label"><?php esc_html_e( 'Tip of the week', 'check-for-broken-links' ); ?></span>
			</span>
			<span class="cbl-dv2-tool-body">
				<span class="cbl-dv2-tool-title"><?php esc_html_e( 'SEO / AEO tip of the week', 'check-for-broken-links' ); ?></span>
				<span class="cbl-dv2-tool-desc"><?php esc_html_e( 'Your links are healthy. Now let visitors listen. Add audio versions of your posts in two minutes with TTSWP, no account needed.', 'check-for-broken-links' ); ?></span>
			</span>
			<span class="cbl-dv2-tip-cta"><?php esc_html_e( 'See this week\'s tip', 'check-for-broken-links' ); ?> <span aria-hidden="true">&rarr;</span></span>
		</a>
	</div>
</section>

<section class="cbl-dv2-bottom">
	<div class="cbl-dv2-card cbl-dv2-attention">
		<div class="cbl-dv2-card-head">
			<span class="cbl-dv2-card-title"><?php esc_html_e( 'Needs attention first', 'check-for-broken-links' ); ?></span>
			<?php if ( $wpcbl_broken_count > 0 ) : ?>
				<a href="<?php echo esc_url( $wpcbl_scan_results_url ); ?>" class="cbl-dv2-link"><?php esc_html_e( 'All results', 'check-for-broken-links' ); ?> <span aria-hidden="true">&rarr;</span></a>
			<?php elseif ( $wpcbl_scan_clean ) : ?>
				<span class="cbl-dv2-pill is-good"><?php esc_html_e( 'All clear', 'check-for-broken-links' ); ?></span>
			<?php else : ?>
				<span class="cbl-dv2-pill is-muted"><?php esc_html_e( 'Empty', 'check-for-broken-links' ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( $wpcbl_broken_count > 0 ) : ?>
			<?php // Scan, problems: $wpcbl_has_scan && $wpcbl_broken_count > 0. ?>
			<div class="cbl-dv2-attention-list">
				<?php foreach ( array_slice( $wpcbl_broken_links, 0, 3 ) as $wpcbl_row ) : ?>
					<?php
					$wpcbl_row_code = isset( $wpcbl_row['code'] ) ? (int) $wpcbl_row['code'] : 0;
					$wpcbl_row_url  = isset( $wpcbl_row['link'] ) ? (string) $wpcbl_row['link'] : '';
					?>
					<div class="cbl-dv2-attention-row">
						<span class="cbl-dv2-pill is-bad"><?php echo esc_html( $wpcbl_row_code > 0 ? (string) $wpcbl_row_code : __( 'Error', 'check-for-broken-links' ) ); ?></span>
						<span class="cbl-dv2-attention-url" title="<?php echo esc_attr( $wpcbl_row_url ); ?>"><?php echo esc_html( $wpcbl_row_url ); ?></span>
						<span class="cbl-dv2-attention-found"><?php echo esc_html( wpcbl_get_post_or_comment_title( $wpcbl_row ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="cbl-dv2-empty">
				<?php if ( $wpcbl_scan_clean ) : ?>
					<span class="cbl-dv2-empty-icon is-good" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"></path></svg></span>
					<span><?php esc_html_e( 'Your last scan found no links that need attention.', 'check-for-broken-links' ); ?></span>
				<?php else : ?>
					<span class="cbl-dv2-empty-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
					<span><?php esc_html_e( 'Broken links land here after your first scan, worst status codes first.', 'check-for-broken-links' ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="cbl-dv2-card cbl-dv2-activity">
		<div class="cbl-dv2-card-title"><?php esc_html_e( 'Scan activity', 'check-for-broken-links' ); ?></div>
		<?php
		// Oldest to newest, the last 8 scans on record. Empty slots keep
		// the chart at 8 columns so one scan does not fill the width.
		$wpcbl_activity  = array_reverse( array_slice( $wpcbl_history, 0, 8 ) );
		$wpcbl_max_total = 1;
		foreach ( $wpcbl_activity as $wpcbl_run ) {
			$wpcbl_max_total = max( $wpcbl_max_total, isset( $wpcbl_run['total'] ) ? (int) $wpcbl_run['total'] : 0 );
		}
		?>
		<div class="cbl-dv2-bars">
			<?php foreach ( $wpcbl_activity as $wpcbl_run ) : ?>
				<?php
				$wpcbl_run_total  = isset( $wpcbl_run['total'] ) ? (int) $wpcbl_run['total'] : 0;
				$wpcbl_run_broken = isset( $wpcbl_run['broken'] ) ? (int) $wpcbl_run['broken'] : 0;
				$wpcbl_run_height = max( 8, (int) round( 100 * $wpcbl_run_total / $wpcbl_max_total ) );
				?>
				<span class="cbl-dv2-bar<?php echo $wpcbl_run_broken > 0 ? ' has-broken' : ''; ?>" style="height:<?php echo (int) $wpcbl_run_height; ?>%" title="<?php echo esc_attr( sprintf(
					/* translators: 1: scan date, 2: links checked, 3: broken links found. */
					__( '%1$s: %2$s links checked, %3$s broken', 'check-for-broken-links' ),
					isset( $wpcbl_run['time'] ) ? date_i18n( 'j M', (int) $wpcbl_run['time'] ) : '',
					number_format_i18n( $wpcbl_run_total ),
					number_format_i18n( $wpcbl_run_broken )
				) ); ?>"></span>
			<?php endforeach; ?>
			<?php for ( $wpcbl_i = count( $wpcbl_activity ); $wpcbl_i < 8; $wpcbl_i++ ) : ?>
				<span class="cbl-dv2-bar is-empty"></span>
			<?php endfor; ?>
		</div>
		<?php if ( count( $wpcbl_activity ) >= 2 ) : ?>
			<div class="cbl-dv2-legend">
				<span><span class="cbl-dv2-dot" aria-hidden="true"></span><?php esc_html_e( 'Clean scan', 'check-for-broken-links' ); ?></span>
				<span><span class="cbl-dv2-dot has-broken" aria-hidden="true"></span><?php esc_html_e( 'Broken links found', 'check-for-broken-links' ); ?></span>
			</div>
		<?php else : ?>
			<p class="cbl-dv2-muted"><?php esc_html_e( 'Your scan trend builds up here once a few scans have run.', 'check-for-broken-links' ); ?></p>
		<?php endif; ?>
	</div>
</section>

</div>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
