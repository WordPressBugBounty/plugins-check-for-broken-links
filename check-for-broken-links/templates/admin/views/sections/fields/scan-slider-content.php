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

<input type="checkbox" id="scan_slider_content" name="wpcbl_check_for_broken_links_settings[scan_slider_content]" <?php checked( $scan_slider_content, 'on' ); ?>>
<label for="scan_slider_content"><?php esc_html_e( 'Scan Smart Slider and Revolution Slider content', 'check-for-broken-links' ); ?></label>
<p class="description"><?php esc_html_e( 'Turn this off if a slider plugin keeps old URLs in its internal cache after you remove them.', 'check-for-broken-links' ); ?></p>
