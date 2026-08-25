<?php
/**
 * TTSWP cross-promotion banner, shown above the topbar on plugin pages.
 *
 * Only appears after the plugin has delivered value (at least one completed
 * scan), when TTSWP is not already installed, and until the user dismisses
 * it. Dismissal is stored as user meta so it never returns.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// The SEO / AEO Tip page has its own install CTA; two identical calls to
// action on one screen compete with each other.
if ( isset( $_GET['page'] ) && 'wpcbl-check-for-broken-links-seo-tip' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return;
}

if ( (int) get_option( 'wpcbl_completed_scans', 0 ) < 1 ) {
	return;
}

if ( file_exists( WP_PLUGIN_DIR . '/text-to-speech-tts/text-to-speech-tts.php' ) ) {
	return;
}

if ( get_user_meta( get_current_user_id(), 'wpcbl_ttswp_banner_dismissed', true ) ) {
	return;
}
?>

<div class="cbl-ttswp-banner" id="wpcbl-ttswp-banner">
	<p class="cbl-ttswp-banner-text">
		<strong><?php esc_html_e( 'Boost engagement and AI visibility:', 'check-for-broken-links' ); ?></strong>
		<?php esc_html_e( 'Add audio versions of your posts with Text to Speech for WP. Listeners stay longer, and accessible audio content strengthens your SEO signals.', 'check-for-broken-links' ); ?>
	</p>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-seo-tip' ) ); ?>" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Try it free', 'check-for-broken-links' ); ?></a>
	<button type="button" class="cbl-ttswp-banner-dismiss" id="wpcbl-ttswp-banner-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'check-for-broken-links' ); ?>">&#10005;</button>
</div>

<script>
( function () {
	var dismiss = document.getElementById( 'wpcbl-ttswp-banner-dismiss' );
	if ( ! dismiss ) {
		return;
	}
	dismiss.addEventListener( 'click', function () {
		var banner = document.getElementById( 'wpcbl-ttswp-banner' );
		if ( banner ) {
			banner.style.display = 'none';
		}
		var body = new URLSearchParams();
		body.append( 'action', 'wpcbl_dismiss_ttswp_banner' );
		body.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'wpcbl_check_for_broken_links' ) ); ?>' );
		fetch( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', credentials: 'same-origin', body: body } );
	} );
} )();
</script>
