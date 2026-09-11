<?php
/**
 * AI Visibility Tracker page: brand mentions overview, platform split,
 * competitor list and tracked prompts, proxied from brokenlinkchecker.io.
 * The shell renders immediately with a loading skeleton, the JS fetches
 * state over admin-ajax and fills #wpcbl-aiv-content. A slow or down SaaS
 * never blocks page render.
 *
 * Cadence (how often a reading is captured) stays server-owned, because
 * each reading has a real cost. "Run check now" in the topbar is the one
 * deliberate, rate-limited exception: at most one manual capture per
 * project per calendar day, enforced on brokenlinkchecker.io, never
 * trusted client-side. See wpcbl_aiv_refresh() in the admin-ajax class.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title = __( 'AI Visibility Tracker', 'check-for-broken-links' );
$wpcbl_aiv_conn    = wpcbl_connect();
$wpcbl_aiv_is_on   = $wpcbl_aiv_conn && $wpcbl_aiv_conn->is_connected();

$wpcbl_topbar_meta    = '';
$wpcbl_topbar_actions = '';
if ( $wpcbl_aiv_is_on ) {
	$wpcbl_topbar_meta = '<span class="cbl-badge cbl-badge-pro">' . esc_html__( 'BETA', 'check-for-broken-links' ) . '</span><span id="wpcbl-aiv-quota" class="cbl-ilo-quota"></span>';

	ob_start();
	?>
	<span id="wpcbl-aiv-run-wrap">
		<button type="button" id="wpcbl-aiv-run" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Run check now', 'check-for-broken-links' ); ?></button>
		<span id="wpcbl-aiv-run-status" class="description"></span>
	</span>
	<?php
	$wpcbl_topbar_actions = ob_get_clean();
}

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_aiv_is_on ) : ?>

	<div class="cbl-card">
		<h2><?php esc_html_e( 'Find out if ChatGPT recommends you or your competitors', 'check-for-broken-links' ); ?></h2>
		<p><?php esc_html_e( 'Connect this site to brokenlinkchecker.io to track your visibility in AI answers. You get a free plan included, and the tracker checks ChatGPT, Perplexity and Google AI for the questions your customers actually ask.', 'check-for-broken-links' ); ?></p>
		<p><?php esc_html_e( 'Example: Someone asks ChatGPT "what is the best broken link checker for WordPress". The tracker shows whether your site is mentioned, which competitors are named instead, and how this changes week by week.', 'check-for-broken-links' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpcbl_connect_start" />
			<?php wp_nonce_field( 'wpcbl_connect_start' ); ?>
			<button type="submit" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Connect site and track AI mentions', 'check-for-broken-links' ); ?></button>
		</form>
	</div>

<?php else : ?>

	<div id="wpcbl-aiv-app">

		<div id="wpcbl-aiv-skeleton">
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
		</div>

		<div id="wpcbl-aiv-error" class="cbl-card" style="display:none;">
			<h2><?php esc_html_e( 'Could not load your AI visibility data', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Something went wrong reaching brokenlinkchecker.io. Please try again.', 'check-for-broken-links' ); ?></p>
			<button type="button" id="wpcbl-aiv-retry" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Retry', 'check-for-broken-links' ); ?></button>
		</div>

		<div id="wpcbl-aiv-content"></div>

	</div>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
