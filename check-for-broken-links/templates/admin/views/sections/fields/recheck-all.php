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

<input type="button" class="cbl-btn" id="wpcbl-recheck-all" value="<?php esc_attr_e( 'Re-check all links', 'check-for-broken-links' ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpcbl_check_for_broken_links' ) ); ?>">
<p class="description"><?php esc_html_e( 'Clears cached link statuses and starts a fresh scan. Links you marked as Not broken stay skipped.', 'check-for-broken-links' ); ?></p>
