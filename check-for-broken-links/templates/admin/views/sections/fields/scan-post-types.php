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

// Same filtered list as the dashboard "ready to scan" count.
$wpcbl_public_types = wpcbl_scannable_post_types();

// All checked by default (empty stored value means everything).
$wpcbl_checked_types = ! empty( $scan_post_types ) ? $scan_post_types : array_keys( $wpcbl_public_types );
?>

<fieldset class="wpcbl-checkbox-group">
	<?php foreach ( $wpcbl_public_types as $wpcbl_type ) : ?>
		<label for="wpcbl-scan-type-<?php echo esc_attr( $wpcbl_type->name ); ?>">
			<input type="checkbox" id="wpcbl-scan-type-<?php echo esc_attr( $wpcbl_type->name ); ?>" name="wpcbl_check_for_broken_links_settings[scan_post_types][]" value="<?php echo esc_attr( $wpcbl_type->name ); ?>" <?php checked( in_array( $wpcbl_type->name, $wpcbl_checked_types, true ) ); ?>>
			<?php echo esc_html( $wpcbl_type->labels->name ); ?>
		</label>
	<?php endforeach; ?>
</fieldset>
<p class="description"><?php esc_html_e( 'Choose which content types are scanned for broken links.', 'check-for-broken-links' ); ?></p>
