<?php
/**
 * Rank Tracker page (3.1.3 layout): keyword positions proxied from
 * brokenlinkchecker.io. The shell renders at once with a skeleton, then
 * cbl-rank-tracker.js fetches state over admin-ajax and draws one of
 * three views into #wpcbl-rank-content: first run (no keywords yet),
 * results, or settings (?view=settings). A slow or down SaaS never
 * blocks the page render.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_rank_conn  = wpcbl_connect();
$wpcbl_rank_is_on = $wpcbl_rank_conn && $wpcbl_rank_conn->is_connected();

// Read-only view switch, no state changes, so no nonce is needed here.
$wpcbl_rank_view = isset( $_GET['view'] ) && 'settings' === $_GET['view'] ? 'settings' : 'tracker'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$wpcbl_rank_url          = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-rank-tracker' );
$wpcbl_rank_settings_url = add_query_arg( 'view', 'settings', $wpcbl_rank_url );

$wpcbl_page_title     = 'settings' === $wpcbl_rank_view && $wpcbl_rank_is_on
	? __( 'Rank Tracker settings', 'check-for-broken-links' )
	: __( 'Rank Tracker', 'check-for-broken-links' );
$wpcbl_topbar_meta    = '';
$wpcbl_topbar_actions = '';

if ( $wpcbl_rank_is_on ) {
	if ( 'settings' === $wpcbl_rank_view ) {
		$wpcbl_topbar_meta = '<span>' . esc_html__( 'Applies to all tracked keywords on this site.', 'check-for-broken-links' ) . '</span>';

		ob_start();
		?>
		<span id="wpcbl-rank-saved" class="cbl-rt-saved" data-idle="<?php esc_attr_e( 'Changes save automatically', 'check-for-broken-links' ); ?>" data-saving="<?php esc_attr_e( 'Saving…', 'check-for-broken-links' ); ?>" data-saved="<?php esc_attr_e( 'Saved', 'check-for-broken-links' ); ?>"><?php esc_html_e( 'Changes save automatically', 'check-for-broken-links' ); ?></span>
		<a href="<?php echo esc_url( $wpcbl_rank_url ); ?>" class="cbl-btn"><?php esc_html_e( 'Back to tracker', 'check-for-broken-links' ); ?></a>
		<?php
		$wpcbl_topbar_actions = ob_get_clean();
	} else {
		// Filled with the project's market ("United States · English") once state loads.
		$wpcbl_topbar_meta = '<span id="wpcbl-rank-market"></span>';

		ob_start();
		?>
		<button type="button" id="wpcbl-rank-export" class="cbl-btn" hidden><?php esc_html_e( 'Export CSV', 'check-for-broken-links' ); ?></button>
		<a href="<?php echo esc_url( $wpcbl_rank_settings_url ); ?>" class="cbl-btn"><?php esc_html_e( 'Settings', 'check-for-broken-links' ); ?></a>
		<button type="button" id="wpcbl-rank-add" class="cbl-btn cbl-btn-primary" hidden><?php esc_html_e( '+ Add keywords', 'check-for-broken-links' ); ?></button>
		<?php
		$wpcbl_topbar_actions = ob_get_clean();
	}
}

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_rank_is_on ) : ?>

	<?php
	$wpcbl_empty = array(
		'tool'         => 'rank',
		'headline'     => __( 'See where your keywords rank on Google and Bing', 'check-for-broken-links' ),
		'body'         => __( 'Connect this site to brokenlinkchecker.io to start tracking your rankings. You get a free plan included, and the tracker shows position changes for each keyword over time.', 'check-for-broken-links' ),
		'example'      => __( 'Example: You track "wordpress broken link checker" and see it move from position 14 to 8 after you update the page. The tracker shows the change, so you know which edits actually worked.', 'check-for-broken-links' ),
		'cta'          => __( 'Connect site and track keywords', 'check-for-broken-links' ),
		'chips'        => array( __( 'No credit card', 'check-for-broken-links' ), __( 'Setup takes a minute', 'check-for-broken-links' ), __( 'Google + Bing', 'check-for-broken-links' ) ),
		'preview_meta' => __( 'Last 30 days', 'check-for-broken-links' ),
	);
	require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/tool-empty.php';
	?>

<?php else : ?>

	<div id="wpcbl-rank-app" class="cbl-rt" data-view="<?php echo esc_attr( $wpcbl_rank_view ); ?>">

		<div id="wpcbl-rank-skeleton" class="cbl-rt-skeleton" aria-hidden="true">
			<div></div>
			<div></div>
			<div></div>
		</div>

		<div id="wpcbl-rank-error" class="cbl-rt-card cbl-rt-error" hidden>
			<h2><?php esc_html_e( 'Could not load your rankings', 'check-for-broken-links' ); ?></h2>
			<p id="wpcbl-rank-error-text"><?php esc_html_e( 'Something went wrong reaching brokenlinkchecker.io. Please try again.', 'check-for-broken-links' ); ?></p>
			<button type="button" id="wpcbl-rank-retry" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Try again', 'check-for-broken-links' ); ?></button>
		</div>

		<div id="wpcbl-rank-content"></div>

	</div>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
