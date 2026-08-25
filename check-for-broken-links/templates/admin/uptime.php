<?php
/**
 * Uptime Monitor page: check history and incident timeline proxied from
 * brokenlinkchecker.io. The shell renders immediately with a loading
 * skeleton; the JS fetches state over admin-ajax and fills
 * #wpcbl-uptime-content. A slow or down SaaS never blocks page render.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title   = __( 'Uptime Monitor', 'check-for-broken-links' );
$wpcbl_uptime_conn  = wpcbl_connect();
$wpcbl_uptime_is_on = $wpcbl_uptime_conn && $wpcbl_uptime_conn->is_connected();

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_uptime_is_on ) : ?>

	<div class="cbl-card">
		<h2><?php esc_html_e( 'Know the moment your site goes down', 'check-for-broken-links' ); ?></h2>
		<p><?php esc_html_e( 'Connect this site to brokenlinkchecker.io, free plan included, and track uptime with incident history.', 'check-for-broken-links' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpcbl_connect_start" />
			<?php wp_nonce_field( 'wpcbl_connect_start' ); ?>
			<button type="submit" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Connect this site', 'check-for-broken-links' ); ?></button>
		</form>
	</div>

<?php else : ?>

	<div id="wpcbl-uptime-app">

		<div id="wpcbl-uptime-skeleton">
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
		</div>

		<div id="wpcbl-uptime-error" class="cbl-card" style="display:none;">
			<h2><?php esc_html_e( 'Could not load your uptime data', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Something went wrong reaching brokenlinkchecker.io. Please try again.', 'check-for-broken-links' ); ?></p>
			<button type="button" id="wpcbl-uptime-retry" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Retry', 'check-for-broken-links' ); ?></button>
		</div>

		<div id="wpcbl-uptime-content"></div>

	</div>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
