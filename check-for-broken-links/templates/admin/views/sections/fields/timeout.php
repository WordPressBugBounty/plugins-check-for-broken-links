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

<input type="number" id="wpcbl-timeout" name="wpcbl_check_for_broken_links_settings[timeout]" value="<?php echo esc_attr( $timeout ); ?>" min="5" max="120" step="1" class="small-text">
<?php esc_html_e( 'seconds', 'check-for-broken-links' ); ?>
<p class="description"><?php esc_html_e( 'Links that take longer than this are marked as a warning, not broken.', 'check-for-broken-links' ); ?></p>
