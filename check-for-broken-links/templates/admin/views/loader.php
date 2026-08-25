<?php
/**
 * Settings - Admin - Views.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Views
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div id="wpcbl_stop_scan_div" class ="wpcbl_none wpcbl-is-scanning">
	<br>
	<h2 id="progress_message" class="wpcbl_success_div">
		<?php echo wp_kses_post( WPCBL_Check_Broken_Links_Utilities::get_loader_image_html() ); ?>
		<?php esc_html_e( 'Scanning your site for dead links... Please hold on, this might take a few moments.', 'check-for-broken-links' ); ?>
		<br>
		<?php esc_html_e( 'Please do not close this page.', 'check-for-broken-links' ); ?>
	</h2>
	<?php // The scan JS fills these live from the wpcbl_scan_progress AJAX action. ?>
	<div class="cbl-scan-progress-track"><span class="cbl-scan-progress-fill" id="wpcbl-scan-progress-fill"></span></div>
	<p class="cbl-scan-progress-text" id="wpcbl-scan-progress-text">&nbsp;</p>
	<br>
</div>
