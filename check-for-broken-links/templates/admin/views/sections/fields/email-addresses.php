<?php
/**
 * Settings - Admin - Views - Sections - Fields.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Views/Sections/Fields
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<div style="margin-top: 10px;">
	<input type="text" id="email_addresses" name="wpcbl_check_for_broken_links_settings[email_addresses]" value="<?php echo esc_attr( $email_addresses ); ?>" <?php disabled( ! $has_email_alerts_access || 'on' !== $email_notifications ); ?>>
	<?php if ( ! $has_email_alerts_access ) : ?>
		<span class="cbl-pro-badge"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></span>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Enter email addresses, separated by commas.', 'check-for-broken-links' ); ?></p>
</div>
