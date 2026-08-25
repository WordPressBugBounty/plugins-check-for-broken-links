<?php
/**
 * SEO / AEO Audit page: the audit report proxied from
 * brokenlinkchecker.io. The shell renders immediately with a loading
 * skeleton; the JS fetches state over admin-ajax and fills
 * #wpcbl-audit-content. A slow or down SaaS never blocks page render.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title  = __( 'SEO / AEO Audit', 'check-for-broken-links' );
$wpcbl_audit_conn  = wpcbl_connect();
$wpcbl_audit_is_on = $wpcbl_audit_conn && $wpcbl_audit_conn->is_connected();

$wpcbl_topbar_meta    = '';
$wpcbl_topbar_actions = '';
if ( $wpcbl_audit_is_on ) {
	$wpcbl_topbar_meta = '<span id="wpcbl-audit-quota" class="cbl-audit-quota"></span>';

	ob_start();
	?>
	<span id="wpcbl-audit-controls" class="cbl-audit-controls" style="display:none;">
		<select id="wpcbl-audit-mode" class="cbl-audit-mode">
			<option value="site"><?php esc_html_e( 'Full site', 'check-for-broken-links' ); ?></option>
			<option value="page"><?php esc_html_e( 'One page', 'check-for-broken-links' ); ?></option>
		</select>
		<input type="text" id="wpcbl-audit-url" class="cbl-audit-url" style="display:none;" placeholder="<?php echo esc_attr( home_url( '/pricing' ) ); ?>" />
		<span id="wpcbl-audit-skipquery-wrap" class="cbl-audit-skip">
			<input type="checkbox" id="wpcbl-audit-skipquery" checked />
			<span><?php esc_html_e( 'Skip ?query URLs', 'check-for-broken-links' ); ?></span>
		</span>
		<button type="button" id="wpcbl-audit-run" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Run audit', 'check-for-broken-links' ); ?></button>
	</span>
	<?php
	$wpcbl_topbar_actions = ob_get_clean();
}

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';
?>

<?php if ( ! $wpcbl_audit_is_on ) : ?>

	<div class="cbl-card">
		<h2><?php esc_html_e( 'See what is holding your search rankings back', 'check-for-broken-links' ); ?></h2>
		<p><?php esc_html_e( 'Connect this site to brokenlinkchecker.io, free plan included, and run a full SEO and AEO audit. You need a connected site before an audit can run.', 'check-for-broken-links' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpcbl_connect_start" />
			<?php wp_nonce_field( 'wpcbl_connect_start' ); ?>
			<button type="submit" class="cbl-btn cbl-btn-primary cbl-btn-lg"><?php esc_html_e( 'Connect this site', 'check-for-broken-links' ); ?></button>
		</form>
	</div>

<?php else : ?>

	<div id="wpcbl-audit-app"
		data-pdf-url="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcbl_seo_audit_pdf' ), 'wpcbl_seo_audit_pdf' ) ); ?>">

		<div id="wpcbl-audit-skeleton">
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
			<div class="cbl-card cbl-rank-skeleton-pulse"></div>
		</div>

		<div id="wpcbl-audit-error" class="cbl-card" style="display:none;">
			<h2><?php esc_html_e( 'Could not load your audit', 'check-for-broken-links' ); ?></h2>
			<p><?php esc_html_e( 'Something went wrong reaching brokenlinkchecker.io. Please try again.', 'check-for-broken-links' ); ?></p>
			<button type="button" id="wpcbl-audit-retry" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Retry', 'check-for-broken-links' ); ?></button>
		</div>

		<div id="wpcbl-audit-content"></div>

	</div>

<?php endif; ?>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
