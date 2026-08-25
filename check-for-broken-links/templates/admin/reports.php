<?php
/**
 * Scan reports page: the rolling history of this site's scans, on every
 * plan. Full crawl reports live on brokenlinkchecker.io.
 *
 * @package WPCBL_Check_Broken_Links/Templates/Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$wpcbl_page_title     = __( 'Scan reports', 'check-for-broken-links' );
$wpcbl_topbar_actions = '';

require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-open.php';

$wpcbl_history = get_option( 'wpcbl_scan_history', array() );
if ( ! is_array( $wpcbl_history ) ) {
	$wpcbl_history = array();
}

// Installs that scanned before history existed still get their last scan.
if ( array() === $wpcbl_history ) {
	$wpcbl_last = get_option( 'wpcbl_last_scan_summary' );
	if ( is_array( $wpcbl_last ) && ! empty( $wpcbl_last['time'] ) ) {
		$wpcbl_history = array( $wpcbl_last );
	}
}

$wpcbl_source_labels = array(
	'manual'    => __( 'Manual', 'check-for-broken-links' ),
	'scheduled' => __( 'Scheduled', 'check-for-broken-links' ),
);
?>

<div class="cbl-card">
	<div class="cbl-reports-head">
		<h2 class="cbl-reports-title"><?php esc_html_e( 'Scan reports', 'check-for-broken-links' ); ?></h2>
		<?php if ( array() !== $wpcbl_history ) : ?>
			<span class="cbl-reports-hint">
				<?php
				/* translators: %d: number of stored scan reports. */
				echo esc_html( sprintf( _n( '%d scan', '%d scans', count( $wpcbl_history ), 'check-for-broken-links' ), count( $wpcbl_history ) ) );
				?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( array() === $wpcbl_history ) : ?>
		<p class="cbl-reports-empty">
			<?php esc_html_e( 'No scans yet. Run one from Dashboard & Scan and it shows up here.', 'check-for-broken-links' ); ?>
		</p>
	<?php else : ?>
		<div class="cbl-reports-row cbl-reports-row-head">
			<span><?php esc_html_e( 'Date', 'check-for-broken-links' ); ?></span>
			<span><?php esc_html_e( 'Source', 'check-for-broken-links' ); ?></span>
			<span><?php esc_html_e( 'Links checked', 'check-for-broken-links' ); ?></span>
			<span><?php esc_html_e( 'Broken', 'check-for-broken-links' ); ?></span>
			<span><?php esc_html_e( 'Duration', 'check-for-broken-links' ); ?></span>
		</div>
		<?php foreach ( $wpcbl_history as $wpcbl_row ) : ?>
			<?php
			$wpcbl_broken = isset( $wpcbl_row['broken'] ) ? (int) $wpcbl_row['broken'] : 0;
			$wpcbl_source = isset( $wpcbl_row['source'] ) ? (string) $wpcbl_row['source'] : '';
			?>
			<div class="cbl-reports-row">
				<span class="cbl-reports-date"><?php echo esc_html( isset( $wpcbl_row['time_str'] ) ? $wpcbl_row['time_str'] : '' ); ?></span>
				<span><span class="cbl-reports-source"><?php echo esc_html( isset( $wpcbl_source_labels[ $wpcbl_source ] ) ? $wpcbl_source_labels[ $wpcbl_source ] : ucfirst( $wpcbl_source ) ); ?></span></span>
				<span><?php echo esc_html( number_format_i18n( isset( $wpcbl_row['total'] ) ? (int) $wpcbl_row['total'] : 0 ) ); ?></span>
				<span class="<?php echo $wpcbl_broken > 0 ? 'cbl-reports-broken' : 'cbl-reports-clean'; ?>"><?php echo esc_html( number_format_i18n( $wpcbl_broken ) ); ?></span>
				<span><?php echo esc_html( sprintf( '%.1fs', isset( $wpcbl_row['duration'] ) ? (float) $wpcbl_row['duration'] : 0 ) ); ?></span>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<p class="cbl-reports-footnote">
	<?php esc_html_e( 'These are this site\'s content scans. Full site crawl reports, with every page a visitor can reach, live on brokenlinkchecker.io.', 'check-for-broken-links' ); ?>
	<a href="<?php echo esc_url( 'https://brokenlinkchecker.io/go/scans?site=' . rawurlencode( home_url() ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open crawl reports', 'check-for-broken-links' ); ?> &#8599;</a>
</p>

<?php
require WPCBL_CHECK_BROKEN_LINKS_TEMPLATES_PATH . 'admin/partials/layout-close.php';
