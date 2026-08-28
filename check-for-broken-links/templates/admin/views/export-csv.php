<?php
/**
 * Settings - Admin - Views.
 *
 * Export row: CSV is free, the other formats are Pro.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin/Views
 * @author Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_export_has_pro = wpcbl_has_pro();
$wpcbl_export_upgrade = admin_url( 'admin.php?page=wpcbl-check-for-broken-links-upgrade' );
$wpcbl_pro_formats    = array(
	'xlsx' => 'XLSX',
	'txt'  => 'TXT',
	'md'   => __( 'MD for AI', 'check-for-broken-links' ),
	'pdf'  => 'PDF',
);
?>

<div class="wpcbl_export_csv_wrap">
	<form name="wpcbl_export_csv_form" class="wpcbl_export_csv_form" id="wpcbl_export_csv_form" action="<?php echo esc_url( admin_url( 'admin.php?page=wpcbl-check-for-broken-links-scan' ) ); ?>" method="post">
		<input type="hidden" name="action" value="wpcbl_export_report"/>
		<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'wpcbl_export_csv_nonce' ) ); ?>"/>
		<span class="cbl-export-label"><?php esc_html_e( 'Export report:', 'check-for-broken-links' ); ?></span>
		<button type="submit" name="format" value="csv" class="cbl-btn"><?php esc_html_e( 'CSV', 'check-for-broken-links' ); ?></button>
		<?php foreach ( $wpcbl_pro_formats as $wpcbl_format => $wpcbl_format_label ) : ?>
			<?php if ( $wpcbl_export_has_pro && 'pdf' !== $wpcbl_format ) : ?>
				<button type="submit" name="format" value="<?php echo esc_attr( $wpcbl_format ); ?>" class="cbl-btn"><?php echo esc_html( $wpcbl_format_label ); ?></button>
			<?php elseif ( $wpcbl_export_has_pro ) : ?>
				<?php // PDF is not shipped yet. Paid plans never see an Upgrade link. ?>
				<span class="cbl-btn cbl-btn-locked" title="<?php esc_attr_e( 'PDF export is on the way.', 'check-for-broken-links' ); ?>"><?php echo esc_html( $wpcbl_format_label ); ?> <span class="cbl-pro-badge"><?php esc_html_e( 'SOON', 'check-for-broken-links' ); ?></span></span>
			<?php else : ?>
				<a href="<?php echo esc_url( $wpcbl_export_upgrade ); ?>" class="cbl-btn cbl-btn-locked"><?php echo esc_html( $wpcbl_format_label ); ?> <span class="cbl-pro-badge"><?php esc_html_e( 'PRO', 'check-for-broken-links' ); ?></span></a>
			<?php endif; ?>
		<?php endforeach; ?>
	</form>
</div>
