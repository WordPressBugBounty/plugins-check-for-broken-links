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

$wpcbl_total_links = isset( $links['total'] ) ? intval( $links['total'] ) : 0;
?>

<div id="wpcbl_no_broken_links_container" class="wpcbl_no_broken_links_container">
	<div id="wpcbl_no_broken_links" class="wpcbl_no_broken_links">
		<h2><?php esc_html_e( 'Congratulations, no dead links found!', 'check-for-broken-links' ); ?></h2>
		<p>
			<?php
			/* translators: %s: total number of links */
			printf( esc_html__( 'We scanned a total of %s links on your site. All clear!', 'check-for-broken-links' ), '<span class="total-links">' . esc_html( $wpcbl_total_links ) . '</span>' );
			?>
		</p>
	</div>
</div>
