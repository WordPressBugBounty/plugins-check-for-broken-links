<?php
/**
 * Scan Results page (redesigned).
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_broken_links_table = new WPCBL_Check_Broken_Links_Admin_Links_List_Table();
$wpcbl_broken_links_table->prepare_items();

// Last-scan summary for the stat cards. The scan JS updates the
// #wpcbl-last-scan-*-visible ids live after a manual scan completes.
$wpcbl_last_scan = get_option( 'wpcbl_last_scan_summary' );
$wpcbl_has_scan  = is_array( $wpcbl_last_scan ) && ! empty( $wpcbl_last_scan['time'] );

// Cleared results are not a first run: the empty state and stat cards word
// themselves differently once at least one scan has completed.
$wpcbl_has_scanned_before = (int) get_option( 'wpcbl_completed_scans', 0 ) >= 1;

$wpcbl_scan_time_str = '';
$wpcbl_scan_duration = 0;
$wpcbl_scan_total    = 0;
$wpcbl_scan_broken   = 0;

if ( $wpcbl_has_scan ) {
	$wpcbl_scan_time_str = isset( $wpcbl_last_scan['time_str'] ) ? $wpcbl_last_scan['time_str'] : date_i18n( 'j F Y \a\t H:i:s', (int) $wpcbl_last_scan['time'] );
	$wpcbl_scan_duration = isset( $wpcbl_last_scan['duration'] ) ? (float) $wpcbl_last_scan['duration'] : 0;
	$wpcbl_scan_total    = isset( $wpcbl_last_scan['total'] ) ? (int) $wpcbl_last_scan['total'] : 0;
	$wpcbl_scan_broken   = isset( $wpcbl_last_scan['broken'] ) ? (int) $wpcbl_last_scan['broken'] : 0;
}

// First-run: what a scan would cover. Mirrors get_data_to_scan(): the
// configured content types (all public types when unset), published only.
// Comments only count when the Pro toggle scans them.
$wpcbl_ready_count  = 0;
$wpcbl_ready_labels = array();
if ( ! $wpcbl_has_scan ) {
	$wpcbl_scan_post_types = wpcbl_scannable_post_types();

	$wpcbl_selected_types = wpcbl_get_option( 'scan_post_types', array() );
	if ( ! empty( $wpcbl_selected_types ) && is_array( $wpcbl_selected_types ) ) {
		$wpcbl_scan_post_types = array_intersect_key( $wpcbl_scan_post_types, array_flip( $wpcbl_selected_types ) );
	}

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

	// The sentence names at most three types, posts/pages/products first,
	// no matter how many are enabled.
	foreach ( array( 'post', 'page', 'product' ) as $wpcbl_priority_type ) {
		if ( isset( $wpcbl_ready_type_labels[ $wpcbl_priority_type ] ) ) {
			$wpcbl_ready_labels[] = $wpcbl_ready_type_labels[ $wpcbl_priority_type ];
			unset( $wpcbl_ready_type_labels[ $wpcbl_priority_type ] );
		}
	}
	$wpcbl_ready_labels = array_slice( array_merge( $wpcbl_ready_labels, array_values( $wpcbl_ready_type_labels ) ), 0, 3 );
}

// Topbar.
$wpcbl_page_title = __( 'Dashboard & Scan', 'check-for-broken-links' );

$wpcbl_topbar_meta = '';
if ( $wpcbl_has_scan ) {
	$wpcbl_topbar_meta = '<span class="cbl-topbar-meta-label"><span class="cbl-topbar-meta-dot"></span>'
		. esc_html__( 'Last scan:', 'check-for-broken-links' ) . '</span> '
		. '<span id="wpcbl-last-scan-time-visible">' . esc_html( $wpcbl_scan_time_str ) . '</span>';
}

// First-run: the hero block owns the primary action, so the topbar stays empty.
$wpcbl_topbar_actions = '';
if ( $wpcbl_has_scan ) {
	$wpcbl_topbar_actions .= '<button type="button" class="cbl-btn" id="wpcbl-clear-results"><span class="cbl-btn-label">'
		. esc_html__( 'Clear Results', 'check-for-broken-links' ) . '</span></button> ';
	$wpcbl_topbar_actions .= '<button type="button" class="cbl-btn cbl-btn-primary" id="wpcbl-manual-scan"><span class="cbl-btn-label">'
		. esc_html__( 'Start Manual Scan', 'check-for-broken-links' ) . '</span></button>';
}

$wpcbl_stat_pending_text = $wpcbl_has_scanned_before ? __( 'After your next scan', 'check-for-broken-links' ) : __( 'After your first scan', 'check-for-broken-links' );

// The rating footer stays out of the first-run view.
$wpcbl_hide_footer = ! $wpcbl_has_scan;

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_has_scan ) : ?>

	<div class="wpcbl-check-for-broken-links-scan" id="wpcbl-check-for-broken-links-scan">

		<?php // The loader banner the scan JS toggles (.wpcbl-is-scanning / .wpcbl_none). ?>
		<?php require_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/loader.php'; ?>

		<div class="cbl-scan-hero" id="wpcbl-empty-state">
			<div class="cbl-scan-hero-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
			</div>
			<?php if ( $wpcbl_has_scanned_before ) : ?>
				<h2><?php esc_html_e( 'No scan results', 'check-for-broken-links' ); ?></h2>
			<?php else : ?>
				<h2><?php esc_html_e( 'Find broken links before Google does', 'check-for-broken-links' ); ?></h2>
			<?php endif; ?>
			<?php if ( $wpcbl_ready_count > 0 ) : ?>
				<p class="cbl-scan-hero-sub">
					<?php
					printf(
						/* translators: 1: number of items, 2: list of content type labels, e.g. "posts, pages and products". */
						esc_html__( 'Ready to scan %1$s items across your %2$s.', 'check-for-broken-links' ),
						esc_html( number_format_i18n( $wpcbl_ready_count ) ),
						esc_html( wp_sprintf_l( '%l', $wpcbl_ready_labels ) )
					);
					?>
				</p>
			<?php endif; ?>
			<button type="button" class="cbl-btn cbl-btn-primary cbl-btn-lg cbl-scan-hero-btn" id="wpcbl-first-scan"><span class="cbl-btn-label"><?php echo esc_html( $wpcbl_has_scanned_before ? __( 'Run a new scan', 'check-for-broken-links' ) : __( 'Run your first scan', 'check-for-broken-links' ) ); ?></span></button>
			<p class="cbl-scan-hero-hint"><?php esc_html_e( 'Typically takes a few minutes. Keep this tab open while it runs.', 'check-for-broken-links' ); ?></p>
		</div>

	</div>

<?php endif; ?>

<div class="cbl-stat-grid<?php echo $wpcbl_has_scan ? '' : ' cbl-stat-grid-3'; ?>">
	<div class="cbl-stat-card">
		<span class="cbl-stat-icon cbl-stat-icon-green" aria-hidden="true">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
		</span>
		<div class="cbl-stat-text">
			<p class="cbl-stat-label"><?php esc_html_e( 'Links checked', 'check-for-broken-links' ); ?></p>
			<div class="cbl-stat-value"<?php echo $wpcbl_has_scan ? '' : ' style="display:none;"'; ?>><span id="wpcbl-last-scan-total-visible"><?php echo esc_html( $wpcbl_has_scan ? number_format_i18n( $wpcbl_scan_total ) : '-' ); ?></span></div>
			<?php if ( ! $wpcbl_has_scan ) : ?>
				<p class="cbl-stat-sub cbl-stat-pending"><?php echo esc_html( $wpcbl_stat_pending_text ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<div class="cbl-stat-card">
		<span class="cbl-stat-icon cbl-stat-icon-red" aria-hidden="true">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
		</span>
		<div class="cbl-stat-text">
			<p class="cbl-stat-label"><?php esc_html_e( 'Broken links', 'check-for-broken-links' ); ?></p>
			<div class="cbl-stat-value <?php echo esc_attr( $wpcbl_has_scan ? ( $wpcbl_scan_broken > 0 ? 'is-broken' : 'is-ok' ) : '' ); ?>"<?php echo $wpcbl_has_scan ? '' : ' style="display:none;"'; ?>><span id="wpcbl-last-scan-broken-visible"><?php echo esc_html( $wpcbl_has_scan ? number_format_i18n( $wpcbl_scan_broken ) : '-' ); ?></span></div>
			<?php if ( ! $wpcbl_has_scan ) : ?>
				<p class="cbl-stat-sub cbl-stat-pending"><?php echo esc_html( $wpcbl_stat_pending_text ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<div class="cbl-stat-card">
		<span class="cbl-stat-icon cbl-stat-icon-purple" aria-hidden="true">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg>
		</span>
		<div class="cbl-stat-text">
			<p class="cbl-stat-label"><?php esc_html_e( 'Automatic scans', 'check-for-broken-links' ); ?></p>
			<?php
			$wpcbl_frequency    = (string) wpcbl_get_option( 'scan_frequency', 'never' );
			$wpcbl_settings_url = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-settings' );
			$wpcbl_freq_labels  = array(
				'daily'   => __( 'Daily', 'check-for-broken-links' ),
				'weekly'  => __( 'Weekly', 'check-for-broken-links' ),
				'monthly' => __( 'Monthly', 'check-for-broken-links' ),
			);
			?>
			<?php if ( wpcbl_has_pro() && isset( $wpcbl_freq_labels[ $wpcbl_frequency ] ) ) : ?>
				<p class="cbl-stat-sub"><?php echo esc_html( $wpcbl_freq_labels[ $wpcbl_frequency ] ); ?> &middot; <a href="<?php echo esc_url( $wpcbl_settings_url ); ?>"><?php esc_html_e( 'Change in Settings', 'check-for-broken-links' ); ?></a></p>
			<?php elseif ( wpcbl_has_pro() ) : ?>
				<p class="cbl-stat-sub"><?php esc_html_e( 'Off', 'check-for-broken-links' ); ?> &middot; <a href="<?php echo esc_url( $wpcbl_settings_url ); ?>"><?php esc_html_e( 'Turn on in Settings', 'check-for-broken-links' ); ?></a></p>
			<?php else : ?>
				<p class="cbl-stat-sub"><?php esc_html_e( 'Off', 'check-for-broken-links' ); ?> &middot; <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' ) ); ?>"><?php esc_html_e( 'Enable with Pro', 'check-for-broken-links' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
	<?php if ( $wpcbl_has_scan ) : ?>
		<div class="cbl-stat-card">
			<span class="cbl-stat-icon cbl-stat-icon-amber" aria-hidden="true">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
			</span>
			<div class="cbl-stat-text">
				<p class="cbl-stat-label"><?php esc_html_e( 'Scan duration', 'check-for-broken-links' ); ?></p>
				<div class="cbl-stat-value"><span id="wpcbl-last-scan-duration-visible"><?php echo esc_html( sprintf( '%.1fs', $wpcbl_scan_duration ) ); ?></span></div>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php if ( ! $wpcbl_has_scan ) : ?>

	<div class="cbl-card cbl-checks-strip">
		<div class="cbl-checks-head">
			<p class="cbl-checks-label"><?php esc_html_e( 'What a scan checks', 'check-for-broken-links' ); ?></p>
			<a class="cbl-checks-settings-link" href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-settings#scan' ) ); ?>"><?php esc_html_e( 'Adjust in Settings', 'check-for-broken-links' ); ?></a>
		</div>
		<div class="cbl-checks-items">
			<span class="cbl-checks-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><?php esc_html_e( 'Links in posts & pages', 'check-for-broken-links' ); ?></span>
			<span class="cbl-checks-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><?php class_exists( 'WooCommerce' ) ? esc_html_e( 'Product descriptions', 'check-for-broken-links' ) : esc_html_e( 'Custom content', 'check-for-broken-links' ); ?></span>
			<span class="cbl-checks-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><?php esc_html_e( 'Images', 'check-for-broken-links' ); ?></span>
			<span class="cbl-checks-item"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg><?php esc_html_e( 'Internal & external URLs', 'check-for-broken-links' ); ?></span>
		</div>
	</div>

<?php else : ?>

	<div class="wpcbl-check-for-broken-links-scan" id="wpcbl-check-for-broken-links-scan">

		<?php // The loader banner the scan JS toggles (.wpcbl-is-scanning / .wpcbl_none). ?>
		<?php require_once WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/views/loader.php'; ?>

		<?php
		$wpcbl_ai_batch_live = wpcbl_has_pro() && 'on' === wpcbl_get_option( 'ai_fix', '' );
		if ( $wpcbl_has_scan ) :
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
		<?php endif; ?>

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
