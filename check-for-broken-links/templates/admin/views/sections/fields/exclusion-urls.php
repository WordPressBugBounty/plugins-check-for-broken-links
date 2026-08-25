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

<textarea id="exclusions" name="wpcbl_check_for_broken_links_settings[exclusion_urls]" rows="5" cols="50" placeholder="<?php echo esc_attr( "/tag/\n?ref=" ); ?>"><?php echo esc_textarea( $exclusion_urls ); ?></textarea>
<p class="description"><?php esc_html_e( 'One rule per line. Any link whose URL contains the text is skipped.', 'check-for-broken-links' ); ?></p>
<p class="description"><?php esc_html_e( 'Common dynamic URLs (cart, admin, login) are excluded automatically.', 'check-for-broken-links' ); ?></p>
