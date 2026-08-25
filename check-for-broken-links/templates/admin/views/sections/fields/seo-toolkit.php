<?php
/**
 * Settings - Admin - Views - Sections - Fields.
 *
 * Free-version SEO Toolkit card: the four Pro SEO features as a read-only
 * list with one PRO badge and one upgrade CTA. Pro renders the individual
 * toggles instead. Expects $upgrade_url from the caller.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Views/Sections/Fields
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_seo_features = array(
	array(
		'name' => __( 'Fix with AI', 'check-for-broken-links' ),
		'desc' => __( 'AI finds a verified working replacement for each broken link.', 'check-for-broken-links' ),
		'icon' => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/><circle cx="12" cy="12" r="3.5"/>',
	),
	array(
		'name' => __( 'Nofollow Broken Links', 'check-for-broken-links' ),
		'desc' => __( 'Adds rel=nofollow to broken links until they are fixed.', 'check-for-broken-links' ),
		'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
	),
	array(
		'name' => __( 'Auto-fix Redirects', 'check-for-broken-links' ),
		'desc' => __( 'Replaces permanently redirected URLs with their final destination.', 'check-for-broken-links' ),
		'icon' => '<polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/>',
	),
	array(
		'name' => __( 'Replacement Suggestions', 'check-for-broken-links' ),
		'desc' => __( 'Suggests archived versions from the Wayback Machine.', 'check-for-broken-links' ),
		'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 7 12 12 15.5 13.5"/>',
	),
);
?>

<div class="cbl-seo-toolkit">
	<div class="cbl-seo-toolkit-head">
		<h3 class="cbl-seo-toolkit-title"><?php esc_html_e( 'SEO Toolkit', 'check-for-broken-links' ); ?></h3>
		<span class="cbl-pro-badge"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></span>
	</div>

	<ul class="cbl-seo-toolkit-list">
		<?php foreach ( $wpcbl_seo_features as $wpcbl_seo_feature ) : ?>
			<li class="cbl-seo-toolkit-item">
				<span class="cbl-seo-toolkit-icon" aria-hidden="true">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $wpcbl_seo_feature['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG path markup defined above. ?></svg>
				</span>
				<span class="cbl-seo-toolkit-text">
					<strong><?php echo esc_html( $wpcbl_seo_feature['name'] ); ?></strong>
					<span class="cbl-seo-toolkit-desc"><?php echo esc_html( $wpcbl_seo_feature['desc'] ); ?></span>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="cbl-seo-toolkit-cta">
		<a href="<?php echo esc_url( $upgrade_url ); ?>" class="cbl-btn cbl-btn-primary"><?php esc_html_e( 'Upgrade to Pro', 'check-for-broken-links' ); ?></a>
	</div>
</div>
