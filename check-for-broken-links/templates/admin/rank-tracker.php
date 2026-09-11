<?php
/**
 * Rank Tracker page: keyword position tracking proxied from
 * brokenlinkchecker.io. The shell renders immediately with a loading
 * skeleton; the JS in rank-tracker.js fetches state over admin-ajax and
 * fills #wpcbl-rank-content. A slow or down SaaS never blocks page render.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title = __( 'Rank Tracker', 'check-for-broken-links' );
$wpcbl_rank_conn   = wpcbl_connect();
$wpcbl_rank_is_on  = $wpcbl_rank_conn && $wpcbl_rank_conn->is_connected();

// Read-only view switch, no state changes, so no nonce is needed here.
$wpcbl_rank_view = isset( $_GET['view'] ) && 'settings' === $_GET['view'] ? 'settings' : 'tracker'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$wpcbl_topbar_actions = '';
if ( $wpcbl_rank_is_on ) {
	$wpcbl_rank_toggle_url = 'settings' === $wpcbl_rank_view
		? admin_url( 'admin.php?page=wpcbl-check-for-broken-links-rank-tracker' )
		: admin_url( 'admin.php?page=wpcbl-check-for-broken-links-rank-tracker&view=settings' );
	$wpcbl_rank_toggle_label = 'settings' === $wpcbl_rank_view
		? __( 'Back to tracker', 'check-for-broken-links' )
		: __( 'Settings', 'check-for-broken-links' );

	ob_start();
	?>
	<a href="#" id="wpcbl-rank-add" class="cbl-btn cbl-btn-primary" style="display:none;"><?php esc_html_e( 'Add keywords', 'check-for-broken-links' ); ?></a>
	<a href="<?php echo esc_url( $wpcbl_rank_toggle_url ); ?>" class="cbl-btn"><?php echo esc_html( $wpcbl_rank_toggle_label ); ?></a>
	<?php
	$wpcbl_topbar_actions = ob_get_clean();
}

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_rank_is_on ) : ?>

	<div class="cbl-card">
		<h2><?php esc_html_e( 'See where your keywords rank on Google and Bing', 'check-for-broken-links' ); ?></h2>
		<p><?php esc_html_e( 'Connect this site to brokenlinkchecker.io to start tracking your rankings. You get a free plan included, and the tracker shows position changes for each keyword over time.', 'check-for-broken-links' ); ?></p>
		<p><?php esc_html_e( 'Example: You track "wordpress broken link checker" and see it move from position 14 to 8 after you update the page. The tracker shows the change, so you know which edits actually worked.', 'check-for-broken-links' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpcbl_connect_start" />
			<?php wp_nonce_field( 'wpcbl_connect_start' ); ?>
			<button type="submit" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Connect site and track keywords', 'check-for-broken-links' ); ?></button>
		</form>
	</div>

<?php else : ?>

	<div id="wpcbl-rank-app" data-wpcbl-rank-view="<?php echo esc_attr( $wpcbl_rank_view ); ?>">

		<div id="wpcbl-rank-skeleton">
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
		</div>

		<div id="wpcbl-rank-error" class="cbl-card" style="display:none;">
			<h2><?php esc_html_e( 'Could not load your rankings', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Something went wrong reaching brokenlinkchecker.io. Please try again.', 'check-for-broken-links' ); ?></p>
			<button type="button" id="wpcbl-rank-retry" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Retry', 'check-for-broken-links' ); ?></button>
		</div>

		<div id="wpcbl-rank-content"></div>

	</div>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
