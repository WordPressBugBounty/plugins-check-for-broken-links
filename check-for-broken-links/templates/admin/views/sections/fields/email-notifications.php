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

<input type="checkbox" id="email_notifications" name="wpcbl_check_for_broken_links_settings[email_notifications]" <?php checked( $email_notifications, 'on' ); ?> <?php disabled( ! $has_email_alerts_access ); ?>>
<label for="email_notifications"><?php esc_html_e( 'Enable email notifications', 'check-for-broken-links' ); ?></label>
<?php if ( ! $has_email_alerts_access ) : ?>
	<span class="cbl-pro-badge"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></span>
<?php endif; ?>
<?php if ( ! $has_email_alerts_access ) : ?>
	<p class="description">
		<?php esc_html_e( 'Email alerts are a Pro feature.', 'check-for-broken-links' ); ?>
		<a href="#cbl-unlock-pro"><?php esc_html_e( 'See Pro features', 'check-for-broken-links' ); ?></a>
	</p>
<?php endif; ?>
