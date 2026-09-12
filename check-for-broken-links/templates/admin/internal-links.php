<?php
/**
 * Internal Link Optimizer page: findings and link suggestions proxied
 * from brokenlinkchecker.io. The shell renders immediately with a
 * loading skeleton, the JS fetches state over admin-ajax and fills
 * #wpcbl-ilo-content. A slow or down SaaS never blocks page render.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title = __( 'Internal Link Optimizer', 'check-for-broken-links' );
$wpcbl_ilo_conn    = wpcbl_connect();
$wpcbl_ilo_is_on   = $wpcbl_ilo_conn && $wpcbl_ilo_conn->is_connected();

$wpcbl_topbar_meta    = '';
$wpcbl_topbar_actions = '';
if ( $wpcbl_ilo_is_on ) {
	$wpcbl_topbar_meta = '<span id="wpcbl-ilo-quota" class="cbl-ilo-quota"></span>';

	ob_start();
	?>
	<button type="button" id="wpcbl-ilo-run" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Run analysis', 'check-for-broken-links' ); ?></button>
	<?php
	$wpcbl_topbar_actions = ob_get_clean();
}

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_ilo_is_on ) : ?>

	<?php
	$wpcbl_empty = array(
		'tool'         => 'links',
		'headline'     => __( 'Find the internal links your pages are missing', 'check-for-broken-links' ),
		'body'         => __( 'Connect this site to brokenlinkchecker.io to run an internal link analysis. You get a free plan included, and the scan shows which pages should link to each other and where.', 'check-for-broken-links' ),
		'example'      => __( 'Example: Your post "How to choose running shoes" mentions marathon training but doesn\'t link to your "Marathon training plan" page. The analysis finds gaps like this and suggests the anchor text to use.', 'check-for-broken-links' ),
		'cta'          => __( 'Connect site and analyze links', 'check-for-broken-links' ),
		'chips'        => array( __( 'No credit card', 'check-for-broken-links' ), __( 'Anchor text suggested', 'check-for-broken-links' ), __( 'Every page scanned', 'check-for-broken-links' ) ),
		'preview_meta' => __( '9 link gaps', 'check-for-broken-links' ),
	);
	require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/tool-empty.php';
	?>

<?php else : ?>

	<div id="wpcbl-ilo-app">

		<div id="wpcbl-ilo-posttypes"></div>

		<div id="wpcbl-ilo-skeleton">
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
		</div>

		<div id="wpcbl-ilo-error" class="cbl-card" style="display:none;">
			<h2><?php esc_html_e( 'Could not load your analysis', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Something went wrong reaching brokenlinkchecker.io. Please try again.', 'check-for-broken-links' ); ?></p>
			<button type="button" id="wpcbl-ilo-retry" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Retry', 'check-for-broken-links' ); ?></button>
		</div>

		<div id="wpcbl-ilo-content"></div>

	</div>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
