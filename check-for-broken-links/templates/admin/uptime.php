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

	<?php
	$wpcbl_empty = array(
		'tool'         => 'uptime',
		'headline'     => __( 'Know the moment your site goes down', 'check-for-broken-links' ),
		'body'         => __( 'Connect this site to brokenlinkchecker.io to monitor uptime around the clock. You get a free plan included, and you get notified when the site stops responding, with a full incident history for every outage.', 'check-for-broken-links' ),
		'example'      => __( 'Example: Your host has a 20-minute outage at 3 AM. You get an alert with the exact time it went down and came back, so you can check for lost orders and hold your host accountable.', 'check-for-broken-links' ),
		'cta'          => __( 'Connect site and start monitoring', 'check-for-broken-links' ),
		'chips'        => array( __( 'No credit card', 'check-for-broken-links' ), __( 'Checks every 5 minutes', 'check-for-broken-links' ), __( 'Email alerts', 'check-for-broken-links' ) ),
		'preview_meta' => __( 'Last 30 days', 'check-for-broken-links' ),
	);
	require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/tool-empty.php';
	?>

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
