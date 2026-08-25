<?php
/**
 * Settings - Admin - Views - Sections - Fields.
 *
 * A Pro-gated toggle: visible but disabled in the free plugin, with a PRO
 * badge and upgrade link. Expects $key, $label, $helper, $value, $has_pro,
 * $upgrade_url from the caller.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Views/Sections/Fields
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<p class="wpcbl-pro-toggle">
	<label for="wpcbl-toggle-<?php echo esc_attr( $key ); ?>">
		<input type="checkbox" id="wpcbl-toggle-<?php echo esc_attr( $key ); ?>" name="wpcbl_check_for_broken_links_settings[<?php echo esc_attr( $key ); ?>]" <?php checked( 'on', $value ); ?> <?php disabled( ! $has_pro ); ?>>
		<?php echo esc_html( $label ); ?>
	</label>
	<?php if ( ! $has_pro ) : ?>
		<span class="cbl-pro-badge"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></span>
	<?php endif; ?>
</p>
<?php if ( ! empty( $helper ) ) : ?>
	<p class="description">
		<?php echo esc_html( $helper ); ?>
		<?php if ( ! $has_pro ) : ?>
			<a href="<?php echo esc_url( $upgrade_url ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'check-for-broken-links' ); ?></a>
		<?php endif; ?>
	</p>
<?php endif; ?>
