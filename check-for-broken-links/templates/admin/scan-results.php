<?php
/**
 * Broken link scan page: the results table lifted out of the old
 * Dashboard & Scan page in 3.0.8. The Dashboard's "Start manual scan" and
 * this page's own "Start new scan" both use it: the Dashboard has no
 * table, so it redirects here on success; this page has one, so it
 * refreshes in place instead (see the JS onResultsPage/hasResultsTable
 * check in the shared admin script).
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_dashboard_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links' );

$wpcbl_broken_links_table = new WPCBL_Check_Broken_Links_Admin_Links_List_Table();
$wpcbl_broken_links_table->prepare_items();

// Last-scan summary for the header meta and the BROKEN/WARNINGS/HEALTHY
// tiles below. The scan JS updates the #wpcbl-last-scan-*-visible ids live
// on an in-place table refresh, matching the same ids the old combined
// page used, so that path keeps working if a trigger ever lands here too.
$wpcbl_last_scan = get_option( 'wpcbl_last_scan_summary' );
$wpcbl_has_scan  = is_array( $wpcbl_last_scan ) && ! empty( $wpcbl_last_scan['time'] );

$wpcbl_scan_time_str = '';
$wpcbl_scan_duration = 0;
$wpcbl_scan_total    = 0;
$wpcbl_scan_broken   = 0;
$wpcbl_scan_warning  = 0;
$wpcbl_scan_healthy  = 0;

if ( $wpcbl_has_scan ) {
	$wpcbl_scan_time_str = isset( $wpcbl_last_scan['time_str'] ) ? $wpcbl_last_scan['time_str'] : date_i18n( 'j F Y \a\t H:i:s', (int) $wpcbl_last_scan['time'] );
	$wpcbl_scan_duration = isset( $wpcbl_last_scan['duration'] ) ? (float) $wpcbl_last_scan['duration'] : 0;
	$wpcbl_scan_total    = isset( $wpcbl_last_scan['total'] ) ? (int) $wpcbl_last_scan['total'] : 0;
	$wpcbl_scan_broken   = isset( $wpcbl_last_scan['broken'] ) ? (int) $wpcbl_last_scan['broken'] : 0;

	$wpcbl_links        = get_option( 'wpcbl_check_for_broken_links_links', array() );
	$wpcbl_scan_warning = isset( $wpcbl_links['warning'] ) && is_array( $wpcbl_links['warning'] ) ? count( $wpcbl_links['warning'] ) : 0;
	$wpcbl_scan_healthy = max( 0, $wpcbl_scan_total - $wpcbl_scan_broken - $wpcbl_scan_warning );
}

// Topbar.
$wpcbl_page_title = __( 'Broken link scan', 'check-for-broken-links' );

$wpcbl_topbar_meta = '';
if ( $wpcbl_has_scan ) {
	$wpcbl_topbar_meta = '<span class="cbl-topbar-meta-label"><span class="cbl-topbar-meta-dot"></span>'
		. esc_html__( 'Last scan:', 'check-for-broken-links' ) . '</span> '
		. '<span id="wpcbl-last-scan-time-visible">' . esc_html( $wpcbl_scan_time_str ) . '</span>';
}

// "Start new scan" lives here too now (not just the Dashboard): someone
// looking at their broken links is exactly who wants to rescan. It reuses
// the same .wpcbl-scan-trigger class every other trigger uses, so the
// disabled state and the progress loader below behave identically.
$wpcbl_topbar_actions = '<button type="button" class="cbl-btn cbl-btn-primary wpcbl-scan-trigger" id="wpcbl-manual-scan"><span class="cbl-btn-label">'
	. esc_html__( 'Start new scan', 'check-for-broken-links' ) . '</span></button>';
if ( $wpcbl_has_scan ) {
	$wpcbl_topbar_actions = '<button type="button" class="cbl-btn" id="wpcbl-clear-results"><span class="cbl-btn-label">'
		. esc_html__( 'Clear Results', 'check-for-broken-links' ) . '</span></button> ' . $wpcbl_topbar_actions;
}

// The rating footer stays out of the first-run view, matching the old page.
$wpcbl_hide_footer = ! $wpcbl_has_scan;

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php
// Explicit marker the shared admin JS keys on to decide "refresh this page
// in place" vs "redirect to the results page" -- deliberately not the
// results-table element itself (that only exists once a scan has already
// run), and not reused for anything else, so this page changing shape
// later can't silently break that decision the way an incidental id would.
?>
<span id="wpcbl-scan-results-page" style="display:none;" aria-hidden="true"></span>

<?php // The loader banner the scan JS toggles (.wpcbl-is-scanning / .wpcbl_none). "Start new scan" above shows its progress here. ?>
<?php require_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/loader.php'; ?>

<p class="cbl-results-breadcrumb">
	<a href="<?php echo esc_url( $wpcbl_dashboard_url ); ?>"><?php esc_html_e( 'Dashboard', 'check-for-broken-links' ); ?></a>
	/ <?php esc_html_e( 'Broken link scan', 'check-for-broken-links' ); ?>
</p>

<?php if ( $wpcbl_has_scan ) : ?>
	<section class="cbl-results-summary">
		<div class="cbl-results-state">
			<span class="cbl-results-state-icon" aria-hidden="true">
				<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m5 13 4.5 4.5L19 7"/></svg>
			</span>
			<div>
				<div class="cbl-results-state-title"><?php esc_html_e( 'Scan complete', 'check-for-broken-links' ); ?></div>
				<div class="cbl-results-state-sub">
					<?php
					printf(
						/* translators: 1: number of links checked, 2: scan duration, e.g. 4.2s. */
						esc_html__( '%1$s links checked, %2$s', 'check-for-broken-links' ),
						'<span id="wpcbl-last-scan-total-visible">' . esc_html( number_format_i18n( $wpcbl_scan_total ) ) . '</span>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'<span id="wpcbl-last-scan-duration-visible">' . esc_html( sprintf( '%.1fs', $wpcbl_scan_duration ) ) . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
				</div>
			</div>
		</div>
		<div class="cbl-results-tiles">
			<div class="cbl-results-tile">
				<span class="cbl-results-tile-label"><?php esc_html_e( 'BROKEN', 'check-for-broken-links' ); ?></span>
				<span id="wpcbl-last-scan-broken-visible" class="cbl-stat-value cbl-results-tile-value<?php echo $wpcbl_scan_broken > 0 ? ' is-broken' : ' is-ok'; ?>"><?php echo esc_html( number_format_i18n( $wpcbl_scan_broken ) ); ?></span>
			</div>
			<div class="cbl-results-tile">
				<span class="cbl-results-tile-label"><?php esc_html_e( 'WARNINGS', 'check-for-broken-links' ); ?></span>
				<span class="cbl-results-tile-value is-warning"><?php echo esc_html( number_format_i18n( $wpcbl_scan_warning ) ); ?></span>
			</div>
			<div class="cbl-results-tile">
				<span class="cbl-results-tile-label"><?php esc_html_e( 'HEALTHY', 'check-for-broken-links' ); ?></span>
				<span class="cbl-results-tile-value is-healthy"><?php echo esc_html( number_format_i18n( $wpcbl_scan_healthy ) ); ?></span>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php if ( ! $wpcbl_has_scan ) : ?>

	<div class="cbl-results-empty" id="wpcbl-results-empty-state">
		<div class="cbl-results-empty-icon">
			<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
		</div>
		<h2><?php esc_html_e( 'No scan results yet', 'check-for-broken-links' ); ?></h2>
		<p class="cbl-results-empty-sub"><?php esc_html_e( 'Broken links show up here once a scan has run.', 'check-for-broken-links' ); ?></p>
		<a href="<?php echo esc_url( $wpcbl_dashboard_url ); ?>" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Go run a scan', 'check-for-broken-links' ); ?></a>
	</div>

<?php else : ?>

	<div class="wpcbl-check-for-broken-links-scan" id="wpcbl-check-for-broken-links-scan">

		<?php
		$wpcbl_ai_batch_live = wpcbl_has_pro() && 'on' === wpcbl_get_option( 'ai_fix', '' );
		?>
		<div class="cbl-card cbl-ai-batch-card" id="wpcbl-ai-fix-batch-bar"<?php echo $wpcbl_scan_broken > 0 ? '' : ' style="display:none;"'; ?>>
			<div class="cbl-ai-batch-head">
				<span class="cbl-ai-batch-title"><?php esc_html_e( 'Fix with AI', 'check-for-broken-links' ); ?></span>
				<span class="cbl-ai-batch-sub"><?php esc_html_e( 'Analyze every broken link and apply verified fixes in one pass.', 'check-for-broken-links' ); ?></span>
			</div>
			<?php if ( $wpcbl_ai_batch_live ) : ?>
				<button type="button" class="cbl-btn cbl-btn-primary" id="wpcbl-ai-fix-batch-start"><?php esc_html_e( 'Fix all with AI', 'check-for-broken-links' ); ?></button>
			<?php elseif ( wpcbl_has_pro() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
					<input type="hidden" name="action" value="wpcbl_enable_ai_fix" />
					<?php wp_nonce_field( 'wpcbl_enable_ai_fix' ); ?>
					<button type="submit" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Turn on Fix with AI', 'check-for-broken-links' ); ?></button>
				</form>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ); ?>" class="cbl-btn cbl-btn-locked"><?php esc_html_e( 'Fix all with AI', 'check-for-broken-links' ); ?> <span class="cbl-pro-badge"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></span></a>
			<?php endif; ?>
		</div>
		<div id="wpcbl-ai-fix-review" class="cbl-card cbl-ai-review-card" style="display:none;"></div>

		<div class="cbl-card">
			<div class="wpcbl-check-for-broken-links-links-table">
				<form method="get">
					<?php $wpcbl_broken_links_table->display(); ?>
				</form>
			</div>

			<?php // Export row - the scan JS shows/hides .wpcbl_export_csv_wrap. ?>
			<?php require_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/export-csv.php'; ?>
		</div>

	</div>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
