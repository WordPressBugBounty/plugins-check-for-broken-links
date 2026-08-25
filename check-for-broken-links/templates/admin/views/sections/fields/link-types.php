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

<fieldset class="wpcbl-checkbox-group">
	<label for="wpcbl-link-type-html">
		<input type="checkbox" id="wpcbl-link-type-html" name="wpcbl_check_for_broken_links_settings[link_types][]" value="html" <?php checked( in_array( 'html', $link_types, true ) ); ?>>
		<?php esc_html_e( 'HTML links (a href)', 'check-for-broken-links' ); ?>
	</label>
	<label for="wpcbl-link-type-image">
		<input type="checkbox" id="wpcbl-link-type-image" name="wpcbl_check_for_broken_links_settings[link_types][]" value="image" <?php checked( in_array( 'image', $link_types, true ) ); ?>>
		<?php esc_html_e( 'Images (img src)', 'check-for-broken-links' ); ?>
	</label>
</fieldset>
<p class="description"><?php esc_html_e( 'Broken images are reported with the type Image in the results table.', 'check-for-broken-links' ); ?></p>
