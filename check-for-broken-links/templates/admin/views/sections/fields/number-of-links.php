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

<div class="cbl-segment-row">
	<div class="cbl-segment" role="radiogroup">
		<input type="radio" id="all_links" name="wpcbl_check_for_broken_links_settings[number_of_links]" value="all" <?php checked( $number_of_links, 'all' ); ?>>
		<label for="all_links"><?php esc_html_e( 'All', 'check-for-broken-links' ); ?></label>
		<input type="radio" id="set_number" name="wpcbl_check_for_broken_links_settings[number_of_links]" value="set_number" <?php checked( $number_of_links, 'set_number' ); ?>>
		<label for="set_number"><?php esc_html_e( 'Set number', 'check-for-broken-links' ); ?></label>
	</div>
	<?php // Inline with the segment; the settings JS toggles it on radio change. ?>
	<input type="number" min="1" id="number_of_links" class="cbl-set-links-number" name="wpcbl_check_for_broken_links_settings[set_links_number]" value="<?php echo esc_attr( $set_number ); ?>" aria-label="<?php esc_attr_e( 'Number of links to scan', 'check-for-broken-links' ); ?>" <?php echo ( 'set_number' === $number_of_links ? '' : 'disabled style="display:none;"' ); ?>>
</div>
